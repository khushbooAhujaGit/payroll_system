<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Entity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class WebMasterController extends Controller
{
    public function index()
    {
        $companies = Company::with('entities')->orderBy('name')->get();
        $entities  = Entity::with('company')->orderBy('name')->get();

        return view('masters.index', compact('companies', 'entities'));
    }

    public function storeCompany(Request $request)
    {
        $validated = $request->validate([
            'name'            => 'required|string|max:200',
            'legal_name'      => 'nullable|string|max:200',
            'cin'             => 'nullable|string|max:21',
            'pan'             => 'nullable|string|max:10',
            'gstin'           => 'nullable|string|max:15',
            'registered_address' => 'nullable|string',
        ]);

        Company::create($validated);
        return redirect()->route('masters')->with('success', 'Company created.');
    }

    public function storeEntity(Request $request)
    {
        $validated = $request->validate([
            'company_id'    => 'required|exists:companies,id',
            'name'          => 'required|string|max:200',
            'code'          => 'required|string|max:20',
            'state'         => 'nullable|string|max:100',
            'city'          => 'nullable|string|max:100',
            'address'       => 'nullable|string',
            'pf_number'     => 'nullable|string|max:22',
            'esic_number'   => 'nullable|string|max:17',
            'pt_number'     => 'nullable|string|max:20',
            'tan'           => 'nullable|string|max:10',
            'is_active'     => 'boolean',
        ]);

        Entity::create($validated);
        return redirect()->route('masters')->with('success', 'Entity created.');
    }

    public function template()
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Entities');
        $headers = ['Company Name', 'Entity Name', 'Entity Code', 'State', 'City', 'PF Number', 'ESIC Number', 'PT Number', 'TAN'];
        foreach ($headers as $i => $h) {
            $sheet->setCellValueByColumnAndRow($i + 1, 1, $h);
        }
        $sheet->fromArray(['Acme Corp', 'Acme Mumbai', 'ACM-MUM', 'Maharashtra', 'Mumbai', 'MH/MUM/123', '', '', 'MUMX12345A'], null, 'A2');

        $writer = new Xlsx($spreadsheet);
        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, 'entities_template.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    public function import(Request $request)
    {
        $request->validate(['file' => 'required|file|mimes:xlsx,xls,csv']);

        try {
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($request->file('file')->getPathname());
            $rows        = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);
            array_shift($rows);

            $count = 0;
            DB::beginTransaction();
            foreach ($rows as $row) {
                if (empty(array_filter($row))) continue;
                [$coName, $entName, $code, $state, $city, $pfNum, $esicNum, $ptNum, $tan] = array_pad($row, 9, null);
                if (!$entName || !$code) continue;

                $company = $coName ? Company::firstOrCreate(['name' => trim($coName)], ['legal_name' => trim($coName)]) : null;
                Entity::updateOrCreate(
                    ['code' => strtoupper(trim($code))],
                    array_filter([
                        'company_id'  => $company?->id,
                        'name'        => trim($entName),
                        'state'       => $state ? trim($state) : null,
                        'city'        => $city  ? trim($city)  : null,
                        'pf_number'   => $pfNum   ? trim($pfNum)   : null,
                        'esic_number' => $esicNum ? trim($esicNum) : null,
                        'pt_number'   => $ptNum   ? trim($ptNum)   : null,
                        'tan'         => $tan     ? strtoupper(trim($tan)) : null,
                        'is_active'   => true,
                    ], fn($v) => $v !== null)
                );
                $count++;
            }
            DB::commit();
            return redirect()->route('masters')->with('success', "{$count} entity(s) imported.");
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->route('masters')->with('error', 'Import failed: ' . $e->getMessage());
        }
    }
}
