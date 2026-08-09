<div class="mb-3">
    <label class="form-label">Name</label>
    <input type="text" name="name" class="form-control" value="{{ old('name', $user->name ?? '') }}" required>
</div>
<div class="mb-3">
    <label class="form-label">Email</label>
    <input type="email" name="email" class="form-control" value="{{ old('email', $user->email ?? '') }}" required>
</div>
<div class="mb-3">
    <label class="form-label">Password {{ isset($user) ? '(leave blank to keep)' : '' }}</label>
    <input type="password" name="password" class="form-control" {{ isset($user) ? '' : 'required' }}>
</div>
<div class="mb-3">
    <label class="form-label">Confirm password</label>
    <input type="password" name="password_confirmation" class="form-control" {{ isset($user) ? '' : 'required' }}>
</div>
<div class="mb-3">
    <label class="form-label">Role</label>
    <select name="role" class="form-control" required>
        <option value="">Select role</option>
        @foreach($roles as $role)
            <option value="{{ $role }}" @selected(old('role', isset($user) ? $user->getRoleNames()->first() : '') === $role)>{{ $role }}</option>
        @endforeach
    </select>
</div>
<div class="mb-3">
    <label class="form-label">Department</label>
    <select name="department_id" class="form-control">
        <option value="">None</option>
        @foreach($departments as $department)
            <option value="{{ $department->id }}" @selected(old('department_id', $user->department_id ?? '') == $department->id)>
                {{ $department->name }}
            </option>
        @endforeach
    </select>
</div>
<div class="mb-3">
    <label class="form-label">District</label>
    <select name="district_id" class="form-control">
        <option value="">None</option>
        @foreach($districts as $district)
            <option value="{{ $district->id }}" @selected(old('district_id', $user->district_id ?? '') == $district->id)>
                {{ $district->name }}
            </option>
        @endforeach
    </select>
</div>
<div class="form-check mb-3">
    <input type="checkbox" class="form-check-input" name="is_active" value="1" id="is_active"
        {{ old('is_active', $user->is_active ?? true) ? 'checked' : '' }}>
    <label class="form-check-label" for="is_active">Active</label>
</div>
