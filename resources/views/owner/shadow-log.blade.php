<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Salary Log</title>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; font-size: 13px; background: #0f172a; color: #e2e8f0; min-height: 100vh; padding: 24px; }
    h1 { font-size: 18px; font-weight: 700; color: #f8fafc; margin-bottom: 4px; }
    .sub { color: #64748b; font-size: 12px; margin-bottom: 20px; }
    .bar { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 16px; align-items: flex-end; }
    input, select { background: #1e293b; border: 1px solid #334155; color: #e2e8f0; padding: 6px 10px; border-radius: 6px; font-size: 12px; }
    button, a.btn { display: inline-block; padding: 6px 14px; border-radius: 6px; font-size: 12px; font-weight: 600; cursor: pointer; border: none; text-decoration: none; }
    .btn-p { background: #3b82f6; color: #fff; }
    .btn-g { background: #16a34a; color: #fff; }
    .btn-s { background: #334155; color: #e2e8f0; }
    table { width: 100%; border-collapse: collapse; background: #1e293b; border-radius: 8px; overflow: hidden; }
    th { background: #0f172a; color: #94a3b8; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .06em; padding: 10px 12px; text-align: left; border-bottom: 1px solid #334155; }
    td { padding: 9px 12px; border-bottom: 1px solid #1e293b; color: #e2e8f0; }
    tr:hover td { background: #263349; }
    .mono { font-family: 'Courier New', monospace; font-size: 12px; }
    .amt { text-align: right; font-variant-numeric: tabular-nums; }
    .badge { display: inline-block; padding: 2px 8px; border-radius: 99px; font-size: 10px; font-weight: 700; text-transform: uppercase; }
    .badge-i { background: #1d4ed8; color: #bfdbfe; }
    .badge-m { background: #065f46; color: #a7f3d0; }
    .pag { display: flex; gap: 6px; margin-top: 16px; flex-wrap: wrap; align-items: center; }
    .pag a, .pag span { padding: 4px 10px; border-radius: 5px; font-size: 12px; background: #1e293b; color: #94a3b8; text-decoration: none; }
    .pag span.active { background: #3b82f6; color: #fff; }
    .total { color: #94a3b8; font-size: 12px; margin-bottom: 10px; }
  </style>
</head>
<body>

  <h1>🔒 Salary Shadow Log</h1>
  <div class="sub">Private plaintext record — not visible anywhere in the main application</div>

  {{-- Filter bar --}}
  <form method="GET" class="bar">
    <input type="hidden" name="t" value="{{ $token }}">
    <div>
      <div style="font-size:11px;color:#64748b;margin-bottom:3px">Month</div>
      <select name="month" onchange="this.form.submit()">
        <option value="">All months</option>
        @foreach($months as $m)
          <option value="{{ $m }}" {{ request('month') === $m ? 'selected' : '' }}>{{ $m }}</option>
        @endforeach
      </select>
    </div>
    <div>
      <div style="font-size:11px;color:#64748b;margin-bottom:3px">Emp ID</div>
      <input type="text" name="emp" value="{{ request('emp') }}" placeholder="Search emp ID…">
    </div>
    <button type="submit" class="btn btn-p">Filter</button>
    <a href="?t={{ $token }}" class="btn btn-s">Reset</a>
    <a href="?t={{ $token }}&month={{ request('month') }}&export=1" class="btn btn-g"
       onclick="this.href='{{ route('owner.shadow-log.export') }}?t={{ $token }}&month={{ request('month') }}'">
      ⬇ Export Excel
    </a>
  </form>

  <div class="total">{{ number_format($logs->total()) }} records &nbsp;·&nbsp; Page {{ $logs->currentPage() }} of {{ $logs->lastPage() }}</div>

  <table>
    <thead>
      <tr>
        <th>#</th>
        <th>Emp ID</th>
        <th>Employee Name</th>
        <th class="amt">Gross / Month</th>
        <th class="amt">CTC / Month</th>
        <th class="amt">CTC Annual</th>
        <th>Effective From</th>
        <th>Month</th>
        <th>Source</th>
        <th>Recorded At</th>
      </tr>
    </thead>
    <tbody>
      @forelse($logs as $i => $log)
        <tr>
          <td class="mono" style="color:#475569">{{ ($logs->currentPage() - 1) * $logs->perPage() + $loop->iteration }}</td>
          <td class="mono">{{ $log->emp_id_text }}</td>
          <td>{{ $log->employee_name }}</td>
          <td class="amt mono">₹{{ number_format($log->gross_monthly, 0) }}</td>
          <td class="amt mono">₹{{ number_format($log->ctc_monthly, 0) }}</td>
          <td class="amt mono">₹{{ number_format($log->ctc_annual, 0) }}</td>
          <td>{{ $log->effective_from?->format('d M Y') }}</td>
          <td class="mono">{{ $log->import_month ?? '—' }}</td>
          <td>
            <span class="badge {{ $log->source === 'import' ? 'badge-i' : 'badge-m' }}">
              {{ $log->source }}
            </span>
          </td>
          <td style="color:#64748b">{{ $log->created_at?->format('d M Y H:i') }}</td>
        </tr>
      @empty
        <tr><td colspan="10" style="text-align:center;padding:40px;color:#475569">No records found.</td></tr>
      @endforelse
    </tbody>
  </table>

  {{-- Pagination --}}
  <div class="pag">
    @if($logs->onFirstPage())
      <span>← Prev</span>
    @else
      <a href="{{ $logs->previousPageUrl() }}&t={{ $token }}">← Prev</a>
    @endif

    @foreach($logs->getUrlRange(max(1, $logs->currentPage()-2), min($logs->lastPage(), $logs->currentPage()+2)) as $page => $url)
      @if($page == $logs->currentPage())
        <span class="active">{{ $page }}</span>
      @else
        <a href="{{ $url }}&t={{ $token }}">{{ $page }}</a>
      @endif
    @endforeach

    @if($logs->hasMorePages())
      <a href="{{ $logs->nextPageUrl() }}&t={{ $token }}">Next →</a>
    @else
      <span>Next →</span>
    @endif
  </div>

</body>
</html>
