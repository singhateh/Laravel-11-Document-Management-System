@extends('layouts.app')

@section('content')
    <div class="container-fluid purple-background">
        <div class="module-container gap-4 justify-content-center">
            <div class="">
                <a href="{{ route('contacts.index') }}">
                    <div class="card module-card red-bg">
                        <div class="card-body">
                            <img src="{{ asset('img/contacts.png') }}" width="80" alt="">
                        </div>
                    </div>
                    <h5 class="card-title">Contacts</h5>
                </a>
            </div>

            <div class="">
                <a href="{{ route('documents.index') }}">
                    <div class="card module-card gray-bg">
                        <div class="card-body">
                            <img src="{{ asset('img/document.png') }}" width="80" alt="">
                        </div>
                    </div>
                    <h5 class="card-title">Documents</h5>
                </a>
            </div>
            <div class="">
                <a href="{{ route('projects.index') }}">
                    <div class="card module-card blue-bg">
                        <div class="card-body">
                            <img src="{{ asset('img/project.png') }}" width="80" alt="">
                        </div>
                    </div>
                    <h5 class="card-title">Projects</h5>
                </a>
            </div>

        </div>
    </div>

    <style>
        .page-content {
            padding: 0%;
            margin: 0%;
        }

        .purple-background {
            background-color: #6a0dadba;
            /* Purple background color */
            color: #fff;
            /* White text color */
            height: 100vh;
        }

        .module-container {
            display: flex;
        }

        .module-container h5 {
            text-align: center;
        }

        .module-card {
            margin-bottom: 20px;
            text-align: center;
            width: 120px;
            margin-top: 2rem;
            cursor: pointer;
        }

        .module-container>* a {
            text-decoration: none;
            color: white;

        }

        .module-card.gray-bg {
            background-color: #919090;
            /* Orange background color */
        }

        .module-card.red-bg {
            background-color: #d16c6c;
            /* Orange background color */
        }

        .module-card.blue-bg {
            background-color: #43cddf;
            /* Orange background color */
        }


        .module-card .card-body {
            padding: 20px;
        }

        .module-card .card-body i {
            color: #fff;
            /* White icon color */
        }

        .module-card .card-title {
            margin-top: 10px;
            font-size: 18px;
        }
    </style>
@endsection
