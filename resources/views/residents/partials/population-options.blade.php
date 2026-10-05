<option value="" @selected(! request('resident_status') && request('lifecycle') !== 'all')>Current / Active</option>
<option value="active" @selected(request('resident_status') === 'active')>Active Resident</option>
<option value="deceased" @selected(request('resident_status') === 'deceased')>Deceased</option>
<option value="moved_out" @selected(request('resident_status') === 'moved_out')>Moved Out</option>
<option value="relocated" @selected(request('resident_status') === 'relocated')>Relocated (Legacy)</option>
<option value="all" @selected(request('resident_status') === 'all' || (! request('resident_status') && request('lifecycle') === 'all'))>All current and historical</option>
