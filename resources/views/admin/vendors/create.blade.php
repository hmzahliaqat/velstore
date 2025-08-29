@extends('admin.layouts.admin')

@section('content')
<div class="card mt-4">
    <div class="card-header card-header-bg text-white">
            <h6 class="d-flex align-items-center mb-0 dt-heading">{{ __('cms.vendors.register_new_vendor') }}</h6>
        </div>
    <div class="card-body">
        <form action="{{ route('admin.vendors.store') }}" method="POST">
            @csrf

            <div class="mb-3">
                <label for="name" class="form-label">{{ __('cms.vendors.vendor_name') }}</label>
                <input type="text" name="name" class="form-control" value="{{ old('name') }}" required>
                @error('name') <small class="text-danger">{{ $message }}</small> @enderror
            </div>

            <div class="mb-3">
                <label for="email" class="form-label">{{ __('cms.vendors.vendor_email') }}</label>
                <input type="email" name="email" class="form-control" value="{{ old('email') }}" required>
                @error('email') <small class="text-danger">{{ $message }}</small> @enderror
            </div>

            <div class="mb-3">
                <label for="phone" class="form-label">{{ __('cms.vendors.phone_optional') }}</label>
                <input type="text" name="phone" class="form-control" value="{{ old('phone') }}">
                @error('phone') <small class="text-danger">{{ $message }}</small> @enderror
            </div>

            <div class="mb-3">
                <label for="password" class="form-label">{{ __('cms.vendors.password') }}</label>
                <input type="password" name="password" class="form-control" required>
                @error('password') <small class="text-danger">{{ $message }}</small> @enderror
            </div>

            <div class="mb-3">
                <label for="password_confirmation" class="form-label">{{ __('cms.vendors.confirm_password') }}</label>
                <input type="password" name="password_confirmation" class="form-control" required>
            </div>

            <div class="mb-3">
                <label for="status" class="form-label">{{ __('cms.vendors.status') }}</label>
                <select name="status" class="form-select" required>
                    <option value="active" {{ old('status')=='active' ? 'selected' : '' }}>{{ __('cms.vendors.active') }}</option>
                    <option value="inactive" {{ old('status')=='inactive' ? 'selected' : '' }}>{{ __('cms.vendors.inactive') }}</option>
                    <option value="banned" {{ old('status')=='banned' ? 'selected' : '' }}>{{ __('cms.vendors.banned') }}</option>
                </select>
                @error('status') <small class="text-danger">{{ $message }}</small> @enderror
            </div>

            <button type="submit" class="btn btn-success">{{ __('cms.vendors.register_button') }}</button>
            <a href="{{ route('admin.vendors.index') }}" class="btn btn-secondary">{{ __('cms.vendors.cancel') }}</a>
        </form>
    </div>
</div>
@endsection
