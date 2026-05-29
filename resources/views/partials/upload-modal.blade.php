{{--
Reusable bulk-upload modal.
Props (passed via @include or component):
$uploadRoute — named route for POST upload (required)
$templateRoute — named route for GET template (optional, shows download button)
$uploadTitle — modal heading text (default: "Bulk Upload")
$uploadHint — instructions paragraph (optional)
$uploadFields — array of extra hidden fields (optional, e.g. ['entity_id' => 1])
--}}
@php
  $uploadTitle = $uploadTitle ?? 'Bulk Upload';
  $uploadHint = $uploadHint ?? null;
  $uploadFields = $uploadFields ?? [];
  $uploadId = $uploadId ?? 'upload-modal';
@endphp

<div class="modal" id="{{ $uploadId }}">
  <div class="mb">
    <div class="mh">
      <span class="mt">{{ $uploadTitle }}</span>
      <button class="mc" onclick="cm('{{ $uploadId }}')">✕</button>
    </div>
    {{-- Template download --}}
    @if(!empty($templateRoute))
      <div
        style="background:var(--acc2);border:1px solid var(--acc);border-radius:8px;padding:10px 14px;margin-bottom:16px;display:flex;align-items:center;gap:10px">
        <span style="font-size:18px">📥</span>
        <div style="flex:1">
          <div style="font-weight:600;font-size:12px;margin-bottom:2px">Download Template</div>
          <div style="font-size:11px;color:var(--t3)">Fill in this Excel template and upload it below.</div>
        </div>
        <a href="{{ route($templateRoute) }}" class="btn acc sm" style="white-space:nowrap">Get Template</a>
      </div>
    @endif
    @if($uploadHint)
      <div style="font-size:12px;color:var(--t3);margin-bottom:14px;line-height:1.6">{{ $uploadHint }}</div>
    @endif
    <form method="POST" action="{{ route($uploadRoute) }}" enctype="multipart/form-data">
      @csrf
      @foreach($uploadFields as $key => $val)
        <input type="hidden" name="{{ $key }}" value="{{ $val }}">
      @endforeach

      <div class="fld" style="margin-bottom:14px">
        <label>Excel File (.xlsx / .xls) *</label>
        <input type="file" name="file" accept=".xlsx,.xls" required
          style="background:var(--s2);border:1.5px dashed var(--b2);border-radius:8px;padding:14px;cursor:pointer;width:100%">
      </div>

      @if(!empty($uploadExtraSlot))
        {!! $uploadExtraSlot !!}
      @endif

      <div class="mf">
        <button type="button" class="btn" onclick="cm('{{ $uploadId }}')">Cancel</button>
        <button type="submit" class="btn acc">📤 Upload &amp; Import</button>
      </div>
    </form>
  </div>
</div>