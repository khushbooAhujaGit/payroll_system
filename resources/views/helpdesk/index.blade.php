@extends('layouts.app')
@section('title', 'Helpdesk')

@section('page_actions')
  <button class="btn acc" onclick="om('ticket-modal')">+ Raise Ticket</button>
  <button class="btn" onclick="om('upload-modal')">📤 Bulk Upload</button>
@endsection

@section('content')

  <div class="g4" style="margin-bottom:16px">
    <div class="sc">
      <div class="lbl">Open</div>
      <div class="val" style="color:var(--red)">{{ $stats['open'] }}</div>
    </div>
    <div class="sc">
      <div class="lbl">In Progress</div>
      <div class="val" style="color:var(--yel)">{{ $stats['in_progress'] }}</div>
    </div>
    <div class="sc">
      <div class="lbl">Resolved</div>
      <div class="val" style="color:var(--grn)">{{ $stats['resolved'] }}</div>
    </div>
  </div>

  <form method="GET" action="{{ route('helpdesk') }}" class="card"
    style="padding:14px 18px;margin-bottom:16px;display:flex;gap:10px;align-items:flex-end">
    <div class="fld" style="min-width:140px">
      <label>Status</label>
      <select name="status">
        <option value="">All</option>
        <option value="open" {{ request('status') === 'open' ? 'selected' : '' }}>Open</option>
        <option value="in_progress" {{ request('status') === 'in_progress' ? 'selected' : '' }}>In Progress</option>
        <option value="resolved" {{ request('status') === 'resolved' ? 'selected' : '' }}>Resolved</option>
        <option value="closed" {{ request('status') === 'closed' ? 'selected' : '' }}>Closed</option>
      </select>
    </div>
    <div class="fld" style="min-width:120px">
      <label>Priority</label>
      <select name="priority">
        <option value="">All</option>
        <option value="urgent" {{ request('priority') === 'urgent' ? 'selected' : '' }}>Urgent</option>
        <option value="high" {{ request('priority') === 'high' ? 'selected' : '' }}>High</option>
        <option value="medium" {{ request('priority') === 'medium' ? 'selected' : '' }}>Medium</option>
        <option value="low" {{ request('priority') === 'low' ? 'selected' : '' }}>Low</option>
      </select>
    </div>
    <button type="submit" class="btn acc">Filter</button>
    <a href="{{ route('helpdesk') }}" class="btn">Reset</a>
  </form>

  <div class="card">
    @if($tickets->isNotEmpty())
      <table>
        <thead>
          <tr>
            <th>Ticket #</th>
            <th>Subject</th>
            <th>Employee</th>
            <th>Category</th>
            <th>Priority</th>
            <th>Status</th>
            <th>Due By</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          @foreach($tickets as $ticket)
            @php
              $priColors = ['urgent' => 'var(--red)', 'high' => 'var(--yel)', 'medium' => 'var(--acc)', 'low' => 'var(--t3)'];
              $stColors = ['open' => 'var(--red)', 'in_progress' => 'var(--yel)', 'resolved' => 'var(--grn)', 'closed' => 'var(--t3)'];
            @endphp
            <tr>
              <td class="mono">{{ $ticket->ticket_number }}</td>
              <td>{{ \Illuminate\Support\Str::limit($ticket->subject, 50) }}</td>
              <td>{{ $ticket->employee?->full_name }}</td>
              <td>{{ $ticket->category?->name ?? '—' }}</td>
              <td><span class="bx"
                  style="color:{{ $priColors[$ticket->priority] ?? 'var(--t3)' }}">{{ ucfirst($ticket->priority) }}</span>
              </td>
              <td><span class="bx"
                  style="color:{{ $stColors[$ticket->status] ?? 'var(--t3)' }}">{{ str_replace('_', ' ', ucwords($ticket->status, '_')) }}</span>
              </td>
              <td style="font-size:11px;color:{{ $ticket->due_by && $ticket->due_by < now() ? 'var(--red)' : 'var(--t3)' }}">
                {{ $ticket->due_by ? \Carbon\Carbon::parse($ticket->due_by)->format('d M, H:i') : '—' }}
              </td>
              <td>
                @if(!in_array($ticket->status, ['resolved', 'closed']))
                  <button class="btn sm" onclick="openResolve({{ $ticket->id }})">Resolve</button>
                @endif
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
      <div style="margin-top:14px">{{ $tickets->links() }}</div>
    @else
      <div class="empty">
        <div class="ico">🎫</div>
        <p>No tickets found.</p>
      </div>
    @endif
  </div>

  {{-- Raise Ticket Modal --}}
  <div class="modal" id="ticket-modal">
    <div class="mb">
      <div class="mh">
        <span class="mt">Raise Ticket</span>
        <button class="mc" onclick="cm('ticket-modal')">✕</button>
      </div>
      <form method="POST" action="{{ route('helpdesk.store') }}">
        @csrf
        <div class="fg2">
          <div class="fld full">
            <label>Category *</label>
            <select name="category_id" required>
              <option value="">Select category</option>
              @foreach($categories as $cat)
                <option value="{{ $cat->id }}">{{ $cat->name }}</option>
              @endforeach
            </select>
          </div>
          <div class="fld full">
            <label>Subject *</label>
            <input type="text" name="subject" required maxlength="200">
          </div>
          <div class="fld">
            <label>Priority *</label>
            <select name="priority" required>
              <option value="low">Low</option>
              <option value="medium" selected>Medium</option>
              <option value="high">High</option>
              <option value="urgent">Urgent</option>
            </select>
          </div>
          <div class="fld full">
            <label>Description *</label>
            <textarea name="description" required rows="4" style="resize:vertical"></textarea>
          </div>
        </div>
        <div class="mf">
          <button type="button" class="btn" onclick="cm('ticket-modal')">Cancel</button>
          <button type="submit" class="btn acc">Raise Ticket</button>
        </div>
      </form>
    </div>
  </div>

  {{-- Resolve Modal --}}
  <div class="modal" id="resolve-modal">
    <div class="mb">
      <div class="mh">
        <span class="mt">Resolve Ticket</span>
        <button class="mc" onclick="cm('resolve-modal')">✕</button>
      </div>
      <form method="POST" id="resolve-form">
        @csrf
        <div class="fld" style="margin-bottom:14px">
          <label>Closure Remarks *</label>
          <textarea name="closure_remarks" required rows="4" style="resize:vertical"></textarea>
        </div>
        <div class="mf">
          <button type="button" class="btn" onclick="cm('resolve-modal')">Cancel</button>
          <button type="submit" class="btn acc">Resolve</button>
        </div>
      </form>
    </div>
  </div>

  @if($errors->any())
    <script>document.addEventListener('DOMContentLoaded', () => om('ticket-modal'))</script>
  @endif
@endsection

@include('partials.upload-modal', [
    'uploadId'      => 'upload-modal',
    'uploadTitle'   => 'Bulk Upload Tickets',
    'uploadRoute'   => 'helpdesk.import',
    'templateRoute' => 'helpdesk.template',
    'uploadHint'    => 'Template columns: Employee ID, Category, Subject, Priority (low/medium/high/urgent), Description. One row per ticket.',
])

@push('scripts')
  <script>
    function openResolve(id) {
      document.getElementById('resolve-form').action = '/helpdesk/' + id + '/resolve';
      om('resolve-modal');
    }
  </script>
@endpush