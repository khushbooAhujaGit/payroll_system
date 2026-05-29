{{-- Shared form fields for Add / Edit Leave Policy modals --}}
@php $edit = $edit ?? false; @endphp

<div class="fg2" style="gap:14px;padding:4px 0 8px">

  <div class="fld full">
    <label class="req">Policy Name</label>
    <input type="text" name="name" required maxlength="100"
      value="{{ $edit ? old('name', $policy?->name ?? '') : old('name') }}"
      placeholder="e.g. Annual Leave – Staff"
      @error('name') class="inp-err" @enderror>
    @error('name') <div class="ferr">{{ $message }}</div> @enderror
  </div>

  <div class="fld full">
    <label class="req">Leave Type</label>
    <select name="leave_type_id" required @error('leave_type_id') class="inp-err" @enderror>
      <option value="">— Select leave type —</option>
      @foreach($leaveTypes as $lt)
        <option value="{{ $lt->id }}"
          {{ ($edit && ($policy?->leave_type_id == $lt->id)) ? 'selected' : '' }}>
          {{ $lt->code }} – {{ $lt->name }}
        </option>
      @endforeach
    </select>
    @error('leave_type_id') <div class="ferr">{{ $message }}</div> @enderror
  </div>

  <div class="fld">
    <label class="req">Days Per Year</label>
    <input type="number" name="days_per_year" required min="0" max="365" step="0.5"
      value="{{ $edit ? old('days_per_year', $policy?->days_per_year ?? '') : old('days_per_year') }}"
      placeholder="e.g. 12"
      @error('days_per_year') class="inp-err" @enderror>
    @error('days_per_year') <div class="ferr">{{ $message }}</div> @enderror
  </div>

  <div class="fld">
    <label class="req">Accrual Type</label>
    <select name="accrual_type" required @error('accrual_type') class="inp-err" @enderror>
      @foreach($accrualTypes as $val => $lbl)
        <option value="{{ $val }}"
          {{ ($edit && ($policy?->accrual_type == $val)) ? 'selected' : '' }}>
          {{ $lbl }}
        </option>
      @endforeach
    </select>
    @error('accrual_type') <div class="ferr">{{ $message }}</div> @enderror
  </div>

  <div class="fld">
    <label>Accrual Days <span style="color:var(--t3);font-size:10px">(optional)</span></label>
    <input type="number" name="accrual_days" min="0" step="0.5"
      value="{{ $edit ? old('accrual_days', $policy?->accrual_days ?? '') : old('accrual_days') }}"
      placeholder="Auto if blank">
  </div>

  <div class="fld">
    <label class="req">Proration</label>
    <select name="proration" required @error('proration') class="inp-err" @enderror>
      @foreach($prorationsTypes as $val => $lbl)
        <option value="{{ $val }}"
          {{ ($edit && ($policy?->proration == $val)) ? 'selected' : '' }}>
          {{ $lbl }}
        </option>
      @endforeach
    </select>
    @error('proration') <div class="ferr">{{ $message }}</div> @enderror
  </div>

  <div class="fld" style="display:flex;flex-direction:column;justify-content:center;gap:10px;padding-top:4px">
    <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:13px">
      <input type="checkbox" name="sandwich_policy" value="1"
        {{ ($edit && ($policy?->sandwich_policy)) ? 'checked' : '' }}
        style="width:16px;height:16px">
      Sandwich Policy
      <span style="font-size:10px;color:var(--t3)">(weekends between leave counted)</span>
    </label>

    <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:13px">
      <input type="checkbox" name="is_active" value="1"
        {{ $edit ? (($policy?->is_active) ? 'checked' : '') : 'checked' }}
        style="width:16px;height:16px">
      Active
    </label>
  </div>

</div>
