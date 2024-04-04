@extends('layouts.app')

@section('content')
    <div class="login-container">
        <div class="login-content">
            <h2>Welcome Back!</h2>
            <form method="POST" action="{{ route('login') }}">
                @csrf
                <div class="form-group">
                    <input type="email" class="form-control" name="email" placeholder="Email Address" required>
                </div>
                <div class="form-group">
                    <input type="password" class="form-control" name="password" placeholder="Password" required>
                </div>
                <div class="form-group">
                    <button type="submit" class="btn btn-primary btn-block">Login</button>
                </div>
            </form>
            @if (Route::has('password.request'))
                <a class="forgot-password" href="{{ route('password.request') }}">Forgot Your Password?</a>
            @endif
        </div>
    </div>

    <style>
        /* Custom styles for login page */
        body {
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
            background-color: #f0f4f7;
            overflow: hidden;
        }

        .page-content {
            padding: 0%;
            margin: 0%;
        }

        .login-container {
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            background-image: url({{ asset('img/document-management-background.jpg') }});
            /* Replace with your image path */
            background-size: cover;
            background-position: center;
        }

        .login-content {
            max-width: 400px;
            padding: 40px;
            background-color: rgba(255, 255, 255, 0.9);
            /* White background with transparency */
            border-radius: 8px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
            text-align: center;
        }

        .login-content h2 {
            margin-bottom: 30px;
            color: #333;
        }

        .form-control {
            width: 100%;
            padding: 10px;
            margin-bottom: 20px;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box;
        }

        .btn-primary {
            background-color: #007bff;
            border: none;
            border-radius: 4px;
            padding: 12px;
            cursor: pointer;
            width: 100%;
        }

        .btn-primary:hover {
            background-color: #0056b3;
        }

        .forgot-password {
            display: block;
            margin-top: 20px;
            color: #007bff;
            text-decoration: none;
        }

        .forgot-password:hover {
            text-decoration: underline;
        }
    </style>
@endsection
