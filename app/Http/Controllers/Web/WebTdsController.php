<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\TdsSlab;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class WebTdsController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('tds_slab_view');

        $currentFy = now()->month >= 4 ? now()->year : now()->year - 1;
        $fy        = $request->get('fy', $currentFy);

        $slabs = TdsSlab::where('financial_year', $fy)
            ->orderBy('regime')->orderBy('income_from')
            ->get()
            ->groupBy('regime');

        $years = TdsSlab::distinct()->orderByDesc('financial_year')->pluck('financial_year');

        return view('tds.index', compact('slabs', 'fy', 'years'));
    }

    public function store(Request $request)
    {
        $this->authorize('tds_slab_manage');

        $v = $request->validate([
            'financial_year'  => 'required|integer|min:2000|max:2099',
            'regime'          => 'required|in:old,new',
            'income_from'     => 'required|numeric|min:0',
            'income_to'       => 'nullable|numeric|gt:income_from',
            'tax_rate'        => 'required|numeric|min:0|max:100',
            'surcharge_rate'  => 'nullable|numeric|min:0|max:100',
            'cess_rate'       => 'nullable|numeric|min:0|max:100',
        ]);

        TdsSlab::create($v);

        return redirect()->route('tds', ['fy' => $v['financial_year']])
            ->with('success', 'TDS slab added.');
    }

    public function update(Request $request, TdsSlab $slab)
    {
        $this->authorize('tds_slab_manage');

        $v = $request->validate([
            'income_from'    => 'required|numeric|min:0',
            'income_to'      => 'nullable|numeric|gt:income_from',
            'tax_rate'       => 'required|numeric|min:0|max:100',
            'surcharge_rate' => 'nullable|numeric|min:0|max:100',
            'cess_rate'      => 'nullable|numeric|min:0|max:100',
        ]);

        $slab->update($v);

        return redirect()->route('tds', ['fy' => $slab->financial_year])
            ->with('success', 'TDS slab updated.');
    }

    public function destroy(TdsSlab $slab)
    {
        $this->authorize('tds_slab_manage');

        $fy = $slab->financial_year;
        $slab->delete();
        return redirect()->route('tds', ['fy' => $fy])
            ->with('success', 'TDS slab deleted.');
    }

    public function template()
    {
        $this->authorize('tds_slab_view');

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('TDS Slabs');
        $headers = ['Financial Year', 'Regime', 'Income From', 'Income To', 'Tax Rate %', 'Surcharge %', 'Cess %'];
        foreach ($headers as $i => $h) {
            $sheet->setCellValue([$i + 1, 1], $h);
        }
        // Sample row
        $sheet->fromArray([2024, 'new', 0, 300000, 0, 0, 4], null, 'A2');
        $sheet->fromArray([2024, 'new', 300001, 600000, 5, 0, 4], null, 'A3');
        $sheet->fromArray([2024, 'new', 600001, 900000, 10, 0, 4], null, 'A4');

        $writer = new Xlsx($spreadsheet);
        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, 'tds_slabs_template.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    public function import(Request $request)
    {
        $this->authorize('tds_slab_manage');

        $request->validate(['file' => 'required|file|mimes:xlsx,xls,csv']);

        try {
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($request->file('file')->getPathname());
            $sheet = $spreadsheet->getActiveSheet();
            $rows  = $sheet->toArray(null, true, true, false);
            $headers = array_shift($rows);   // skip header row

            $count = 0;
            DB::beginTransaction();
            foreach ($rows as $row) {
                if (empty(array_filter($row))) continue;
                [$fy, $regime, $from, $to, $rate, $surcharge, $cess] = array_pad($row, 7, null);
                if (!$fy || !$regime) continue;
                TdsSlab::updateOrCreate(
                    ['financial_year' => (int)$fy, 'regime' => strtolower(trim($regime)), 'income_from' => (float)$from],
                    ['income_to' => $to ? (float)$to : null, 'tax_rate' => (float)$rate, 'surcharge_rate' => (float)$surcharge, 'cess_rate' => (float)$cess]
                );
                $count++;
            }
            DB::commit();
            return redirect()->route('tds')->with('success', "{$count} TDS slab(s) imported.");
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->route('tds')->with('error', 'Import failed: ' . $e->getMessage());
        }
    }
}
