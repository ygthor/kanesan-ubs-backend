@extends('layouts.auth')

@section('title', 'Login - Kanesan UBS Backend')

@section('subtitle', 'Sign in to your account')

@section('content')
    <form method="POST" action="{{ route('login') }}">
        @csrf
        
        <div class="mb-3">
            <label for="login" class="form-label">Email or Username</label>
            <input type="text" class="form-control @error('login') is-invalid @enderror" 
                   id="login" name="login" value="{{ old('login') }}" required autofocus 
                   placeholder="Enter your email or username">
            @error('login')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="mb-3">
            <label for="password" class="form-label">Password</label>
            <input type="password" class="form-control @error('password') is-invalid @enderror" 
                   id="password" name="password" required placeholder="Enter your password"
                   autocomplete="current-password">
            @error('password')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="mb-3 form-check">
            <input type="checkbox" class="form-check-input" id="remember" name="remember" 
                   {{ old('remember') ? 'checked' : '' }}>
            <label class="form-check-label" for="remember">Remember me</label>
        </div>

        <button type="submit" class="btn btn-primary w-100">Sign In</button>
    </form>
@endsection
