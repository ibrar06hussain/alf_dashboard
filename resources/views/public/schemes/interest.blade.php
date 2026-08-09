<div id="express-interest" class="border rounded-3 p-4 bg-white shadow-sm">
    <h5 class="mb-3">Express Interest</h5>
    <p class="small text-muted">Register your interest in this scheme. We will notify you when applications open.</p>

    <form method="post" action="{{ route('schemes.interest', $scheme->slug) }}">
        @csrf
        <div class="mb-3">
            <label class="form-label">Full Name <span class="text-danger">*</span></label>
            <input type="text" name="full_name" class="form-control @error('full_name') is-invalid @enderror" value="{{ old('full_name') }}" required>
            @error('full_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="mb-3">
            <label class="form-label">CNIC (13 digits) <span class="text-danger">*</span></label>
            <input type="text" name="cnic" class="form-control js-cnic @error('cnic') is-invalid @enderror" value="{{ old('cnic') }}" maxlength="15" inputmode="numeric" required>
            @error('cnic')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="mb-3">
            <label class="form-label">Phone <span class="text-danger">*</span></label>
            <input type="text" name="phone" class="form-control @error('phone') is-invalid @enderror" value="{{ old('phone') }}" required>
            @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="mb-3">
            <label class="form-label">District</label>
            <select name="district_id" class="form-select @error('district_id') is-invalid @enderror">
                <option value="">Select district</option>
                @foreach($districts as $district)
                    <option value="{{ $district->id }}" @selected(old('district_id') == $district->id)>{{ $district->name }}</option>
                @endforeach
            </select>
            @error('district_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="mb-3">
            <label class="form-label">Email</label>
            <input type="email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email') }}">
            @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="mb-3">
            <label class="form-label">Message</label>
            <textarea name="message" rows="3" class="form-control @error('message') is-invalid @enderror">{{ old('message') }}</textarea>
            @error('message')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <button type="submit" class="btn famie-btn w-100">Submit Interest</button>
    </form>
</div>

@push('scripts')
<script>
document.querySelectorAll('.js-cnic').forEach(function (input) {
    input.addEventListener('input', function () {
        var digits = this.value.replace(/\D/g, '').slice(0, 13);
        this.value = digits;
    });
});
</script>
@endpush
