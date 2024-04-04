@extends('layouts.app')
@section('content')
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css"> <!-- jQuery UI CSS -->

    <!-- Font Awesome CSS -->
    @include('folders.modals.addFolder')
    @include('folders.modals.addTag')

    <div id="renderFolderTableHtml">
        @include('folders.table')
    </div>

    <style>
        /* public/css/custom.css */

        .custom-table {
            width: 100%;
            border-collapse: collapse;
            border: 1px solid #ccc;
        }

        .custom-table th,
        .custom-table td {
            padding: 8px;
            border: 1px solid #ccc;
        }

        .custom-table th {
            background-color: #f2f2f2;
        }

        .custom-table tbody tr:nth-child(even) {
            background-color: #f9f9f9;
        }

        /* Add more custom styles as needed */
    </style>

    <style>
        /* Badge style */
        .badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 20px;
            background-color: #007bff;
            /* Blue background color */
            color: #fff;
            /* White text color */
            font-weight: bold;
            text-transform: uppercase;
            font-size: 14px;
        }

        /* Different badge colors */
        .success {
            background-color: #28a745;
        }

        /* Green */
        .warning {
            background-color: #ffc107;
        }

        /* Yellow */
        .danger {
            background-color: #dc3545;
        }

        /* Red */
        .info {
            background-color: #17a2b8;
        }

        /* Cyan */

        /* Customizing badge size */
        .large {
            padding: 10px 20px;
            font-size: 18px;
            border-radius: 30px;
        }

        .small {
            padding: 3px 6px;
            font-size: 12px;
            border-radius: 15px;
        }

        /* Customizing badge position */
        .position-top-right {
            position: absolute;
            top: 10px;
            right: 10px;
        }

        .position-top-left {
            position: absolute;
            top: 10px;
            left: 10px;
        }

        .position-bottom-right {
            position: absolute;
            bottom: 10px;
            right: 10px;
        }

        .position-bottom-left {
            position: absolute;
            bottom: 10px;
            left: 10px;
        }
    </style>

    <style>
        /* Basic table styling */
        table {
            border-collapse: collapse;
            width: 100%;
        }

        th,
        td {
            border: 1px solid #dddddd;
            text-align: left;
            padding: 8px;
        }

        th {
            background-color: #f2f2f2;
        }

        /* Tree structure styles */
        .subfolder,
        subfolder td>label {
            margin-right: 10rem !important;
            font-size: 12px !important;
        }
    </style>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script> <!-- Include jQuery -->
    <script src="https://code.jquery.com/ui/1.12.1/jquery-ui.js"></script> <!-- Include jQuery UI -->

    <script>
        function createFolderModal() {
            $('#createFolderModal').modal('show');
        }
    </script>

    <script>
        // Function to add input row
        function addInputRow() {
            // Clone the last input row
            var newInputRow = $('.inputRow:last').clone();

            // Clear input value in the new row
            newInputRow.find('input[type="text"]').val('');

            // Append the new row to the input container
            $('#inputContainer').append(newInputRow);

            // Focus on the new input field
            newInputRow.find('input[type="text"]').focus();
        }

        // Function to remove input row
        function removeInputRow(element) {
            if ($('.inputRow').length > 1) {
                $(element).closest('.inputRow').remove();
            }
        }

        // Capture Enter key press event on input fields
        function tagsEnter(event) {
            if (event.which == 13) { // Check if Enter key is pressed
                addInputRow(); // Add a new input row
                event.preventDefault(); // Prevent default Enter key behavior (form submission)
            }
        }

        // Initialize jQuery UI sortable
        $('#inputContainer').sortable({
            axis: 'y', // Allow sorting vertically
            handle: '.fa-arrows', // Use inputRow class as the handle for dragging
            opacity: 0.5, // Set opacity while dragging
            cursor: 'move' // Set cursor style while dragging
        });


        // Capture blur event on input fields
        $(document).on('blur', 'input[type="text"]', function() {
            var lastRowInputs = $('.inputRow:last input[type="text"]');
            if (lastRowInputs.length === 2 && lastRowInputs[0].value.trim() === '' && lastRowInputs[1].value
                .trim() === '') {
                $('.inputRow:last').remove();
            }
        });
    </script>
@endsection
