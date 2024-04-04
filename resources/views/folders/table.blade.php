<div id="error-message"></div>

<style>
    /* #error-message {
        color: red;
        text-align: center;
    } */

    /* Style for parent rows */
    .tree-parent {
        cursor: pointer;
    }

    /* Style for toggle icon */
    .toggle-icon {
        margin-right: 5px;
    }

    /* Style for child lists */
    .child-list {
        margin-left: 20px;
        /* Indent child rows */
        list-style: none;
        /* Remove default list bullets */
    }

    /* Style for subfolder lists */
    .subfolder-list {
        margin-left: 20px;
        /* Indent subfolders */
        list-style: none;
        /* Remove default list bullets */
    }

    /* Style for category lists */
    .category-list {
        margin-left: 20px;
        /* Indent categories */
        list-style: none;
        /* Remove default list bullets */
    }

    /* Example styling for badges */
    .badge {
        margin-right: 5px;
        padding: 3px 8px;
        border-radius: 5px;
        background-color: #007bff;
        color: #fff;
    }

    /* Example styling for parent rows hover effect */
    .tree-parent:hover {
        background-color: #f2f2f2;
    }
</style>

@if ($folders->isNotEmpty())
    <table class="custom-table" id="folders-table">
        <thead>
            <tr>
                <th><input type="checkbox" id="checkAll"></th>
                <th>Workspaces</th>
                <th>Tag Categories</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($folders as $folder)
                <tr class="tree-parent" data-id="{{ $folder->id }}">
                    <td><input type="checkbox" name="folder_ids[]" value="{{ $folder->id }}"></td>
                    <td>
                        @if ($folder->subfolders->isNotEmpty())
                            <i class="fa fa-caret-right toggle-icon" onclick="toggleChildRows(this)"></i>
                        @endif
                        <i class="fa fa-arrows"></i> {{ $folder->name }}

                        <ul class="child-list" style="display: none;"> <!-- Nested UL for child rows -->
                            @foreach ($folder->subfolders as $subfolder)
                                <li class="tree-child" data-id="{{ $subfolder->id }}">
                                    <input type="checkbox" name="folder_ids[]" value="{{ $subfolder->id }}">
                                    <i class="fa fa-arrows"></i> {{ $subfolder->name }}
                                    <ul class="subfolder-list"> <!-- Nested UL for subfolders -->
                                        @foreach ($subfolder->subfolders as $subsubfolder)
                                            <li class="tree-child" data-id="{{ $subsubfolder->id }}">
                                                <input type="checkbox" name="folder_ids[]"
                                                    value="{{ $subsubfolder->id }}">
                                                <i class="fa fa-arrows"></i> {{ $subsubfolder->name }}
                                                <ul class="category-list"> <!-- Nested UL for categories -->
                                                    @foreach ($subsubfolder->categories as $category)
                                                        <li>
                                                            <label for=""
                                                                class="badge small info">{{ $category->name }}</label>
                                                        </li>
                                                    @endforeach
                                                </ul>
                                            </li>
                                        @endforeach
                                    </ul>
                                    <ul class="category-list"> <!-- Nested UL for categories -->
                                        @foreach ($subfolder->categories as $category)
                                            <li>
                                                <label for=""
                                                    class="badge small info">{{ $category->name }}</label>
                                            </li>
                                        @endforeach
                                    </ul>
                                </li>
                            @endforeach
                        </ul>

                    </td>
                    <td>
                        @foreach ($folder->categories as $category)
                            <label for="" class="badge small info">{{ $category->name }}</label>
                        @endforeach
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
@else
    <x-notFound message="No Folder " />
@endif



<!-- jQuery library -->
<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>

<!-- jQuery UI library with draggable and droppable modules -->
<script src="https://code.jquery.com/ui/1.12.1/jquery-ui.min.js"></script>


<script>
    function downloadFolder() {
        // Get selected folder IDs
        var folderIds = [];
        $('input[name="folder_ids[]"]:checked').each(function() {
            folderIds.push($(this).val());
        });

        // Send AJAX request to fetch folder details
        $.ajax({
            url: '/folders/details',
            method: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                folder_ids: folderIds
            },
            success: function(response) {
                // Send AJAX request to download zip file
                $.ajax({
                    url: '/folders/download-zip',
                    method: 'POST',
                    data: {
                        _token: '{{ csrf_token() }}',
                        folders: response.folders
                    },
                    xhrFields: {
                        responseType: 'blob' // Set response type to blob
                    },
                    success: function(data) {
                        // Create blob URL for the ZIP file
                        var blob = new Blob([data], {
                            type: 'application/zip'
                        });
                        var url = window.URL.createObjectURL(blob);

                        // Create a temporary link element
                        var link = document.createElement('a');
                        link.href = url;
                        link.download = 'LLaMediaDocument.zip'; // Specify the download filename
                        document.body.appendChild(link);
                        link.click();

                        // Cleanup
                        document.body.removeChild(link);
                        window.URL.revokeObjectURL(url);
                    },
                    error: function(xhr, status, error) {
                        // Display validation error message
                        // console.error('Error generating zip file:', error);
                        var errorMessage = xhr.responseJSON ? xhr.responseJSON.error :
                            'Error generating zip file, the folder might be empty and you can not zip an empty folder';
                        // alert(errorMessage);
                        $('#error-message').html('<div class="p-2 bg-danger text-white">' +
                            errorMessage +
                            '</div>');
                    }
                });
            },
            error: function(xhr) {
                if (xhr.status === 422) {
                    var errors = xhr.responseJSON.errors;

                    // Clear existing error messages
                    $('.is-invalid').removeClass('is-invalid');
                    $('.invalid-feedback').remove();

                    for (var key in errors) {
                        $('#' + key).addClass('is-invalid'); // Add is-invalid class to input
                        $('#' + key).after('<div class="invalid-feedback">' + errors[key][0] + '</div>');
                        $('#error-message').html('<div class="p-2 bg-danger text-white">' + errors[key][0] +
                            '</div>');
                    }

                } else {
                    // Handle other errors here
                    console.error(xhr);
                }
            }
        });
    }

    $(document).ready(function() {
        // Check All checkbox
        $('#checkAll').change(function() {
            $('input[name="folder_ids[]"]').prop('checked', $(this).prop('checked'));
            updateDeleteButton(); // Call updateDeleteButton function when Check All checkbox changes
        });

        // Individual checkbox
        $('input[name="folder_ids[]"]').change(function() {
            if ($(this).prop('checked') == false) {
                $('#checkAll').prop('checked', false);
            } else {
                if ($('input[name="folder_ids[]"]:checked').length == $('input[name="folder_ids[]"]')
                    .length) {
                    $('#checkAll').prop('checked', true);
                }
            }
            updateDeleteButton(); // Call updateDeleteButton function when individual checkboxes change
        });
    });


    function toggleChildRows(icon) {
        var parentRow = $(icon).closest('.tree-parent');
        var childList = parentRow.find('.child-list');
        childList.slideToggle();
        $(icon).toggleClass('fa-caret-right fa-caret-down');
    }

    // Function to setup the sortable lists
    window.setupSortableLists = function() {
        // Initialize sortable for parent rows
        $('#folders-table tbody').sortable({
            handle: '.fa-arrows',
            placeholder: 'holder',
            tolerance: 'pointer',
            revert: 300,
            forcePlaceholderSize: true,
            opacity: 0.5,
            scroll: true,
            items: "> .tree-parent", // Only allow sorting of parent rows
            update: function(event, ui) {
                orderFolder();
            }
        });

        // Initialize sortable for child rows
        $('.child-list').sortable({
            handle: '.fa-arrows',
            placeholder: 'holder',
            tolerance: 'pointer',
            revert: 300,
            forcePlaceholderSize: true,
            opacity: 0.5,
            scroll: true,
            connectWith: '.child-list',
            update: function(event, ui) {
                orderChildFolder($(this).closest('.tree-parent'));
            }
        });
    }

    function orderFolder() {
        var csrfToken = $('meta[name="csrf-token"]').attr('content');
        var positions = {};
        $('#folders-table tbody > .tree-parent').each(function(index) {
            positions[$(this).data('id')] = index + 1;
        });

        $.ajax({
            url: '{{ route('folders.updatePositions') }}',
            method: 'POST',
            data: {
                _token: csrfToken,
                positions: positions
            },
            success: function(response) {
                console.log('Positions updated successfully for parent rows');
            },
            error: function(xhr, status, error) {
                console.error('Error updating positions for parent rows:', error);
                $('#error-message').text('Server Error: ' + xhr.responseText);
            }
        });
    }

    function orderChildFolder(parentRow) {
        var csrfToken = $('meta[name="csrf-token"]').attr('content');
        var parentId = parentRow.data('id');
        var positions = {};

        parentRow.find('.child-list > .tree-child').each(function(index) {
            positions[$(this).data('id')] = index + 1;
        });

        $.ajax({
            url: '{{ route('folders.updateChildPositions') }}',
            method: 'POST',
            data: {
                _token: csrfToken,
                parent_id: parentId,
                positions: positions
            },
            success: function(response) {
                console.log('Positions updated successfully for child rows of parent with ID: ' + parentId);
            },
            error: function(xhr, status, error) {
                console.error('Error updating positions for child rows of parent with ID ' + parentId + ':',
                    error);
                $('#error-message').text('Server Error: ' + xhr.responseText);
            }
        });
    }


    function deleteSelectedRecord() {
        deleteSelectedFolders();
    }

    // Function to update delete button
    function updateDeleteButton() {
        if ($('input[name="folder_ids[]"]:checked').length > 0) {
            $('#delete-button').show();
            $('#error-message').html('');
        } else {
            $('#delete-button').hide();
        }
    }

    function deleteSelectedFolders() {
        var folderIds = $('input[name="folder_ids[]"]:checked').map(function() {
            return $(this).val();
        }).get();

        $.ajax({
            url: '{{ route('folders.deleteSelecetdFolder') }}',
            method: 'POST',
            data: {
                _token: $('meta[name="csrf-token"]').attr('content'),
                folder_ids: folderIds
            },
            success: function(response) {
                $('#error-message').text(response.message);
                $('#renderFolderTableHtml').html(response.html);
                localStorage.setItem('selectedFolderId', '');
                localStorage.setItem('selectedShareDocumentFolder', '');

            },
            error: function(xhr, status, error) {
                var errors = xhr.responseJSON.errors;
                $('#error-message').text(errors);
            }
        });
    }

    $(function() {
        setupSortableLists();
        updateDeleteButton(); // Update delete button initially
    });
</script>
