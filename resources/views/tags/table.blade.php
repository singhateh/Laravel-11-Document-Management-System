<div id="error-message"></div>

<table class="custom-table" id="folders-table">
    <thead>
        <tr>
            <th><input type="checkbox" id="checkAll"></th>
            <th>Workspace</th>
            <th>Category</th>
            <th>Tags</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($folders as $folder)
            <tr class="tree-parent" data-id="{{ $folder->id }}">
                <td><input type="checkbox" name="folder_ids[]" value="{{ $folder->id }}"></td>
                <td>
                    <i class="fa fa-arrows"></i> {{ $folder->name }}
                    @foreach ($folder->subfolders as $subfolder)
                        / {{ $subfolder->name }}
                    @endforeach
                </td>
                <td>
                    @foreach ($folder->categories as $category)
                        <span class="badge small info">{{ $category->name }}</span>
                        @foreach ($category->tags as $tag)
                            <span class="badge small">{{ $tag->name }}</span>
                        @endforeach
                    @endforeach
                </td>
            </tr>
        @endforeach
    </tbody>


</table>

<!-- jQuery library -->
<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>

<!-- jQuery UI library with draggable and droppable modules -->
<script src="https://code.jquery.com/ui/1.12.1/jquery-ui.min.js"></script>



<script>
    $(document).ready(function() {
        // Get CSRF token from meta tag
        var csrfToken = $('meta[name="csrf-token"]').attr('content');

        // Initialize sortable for table rows
        $('#folders-table tbody').sortable({
            axis: 'y', // Allow sorting vertically
            handle: '.fa-arrows', // Use .fa-arrows class as the handle for dragging
            opacity: 0.5, // Set opacity while dragging
            cursor: 'move', // Set cursor style while dragging
            stop: function(event, ui) {
                // Get the updated positions
                var positions = {};
                $('#folders-table tbody tr').each(function(index) {
                    positions[$(this).data('id')] = index + 1;
                });

                // Send AJAX request to update positions
                $.ajax({
                    url: '{{ route('folders.updatePositions') }}', // Route to handle the request
                    method: 'POST',
                    data: {
                        _token: csrfToken, // Include CSRF token in the request
                        positions: positions
                    },
                    success: function(response) {
                        console.log('Positions updated successfully');
                    },
                    error: function(xhr, status, error) {
                        console.error('Error updating positions:', error);
                        $('#error-message').text('Server Error: ' + xhr.responseText);
                    }
                });
            }
        });
    });


    // function downloadFolder() {
    //     // Get selected folder IDs
    //     var folderIds = [];
    //     $('input[name="folder_ids[]"]:checked').each(function() {
    //         folderIds.push($(this).val());
    //     });

    //     // Send AJAX request to fetch folder details
    //     $.ajax({
    //         url: '/folders/details',
    //         method: 'POST',
    //         data: {
    //             _token: '{{ csrf_token() }}',
    //             folder_ids: folderIds
    //         },
    //         success: function(response) {
    //             // Send AJAX request to download zip file
    //             $.ajax({
    //                 url: '/folders/download-zip',
    //                 method: 'POST',
    //                 data: {
    //                     _token: '{{ csrf_token() }}',
    //                     folders: response.folders
    //                 },
    //                 xhrFields: {
    //                     responseType: 'blob' // Set response type to blob
    //                 },
    //                 success: function(data) {
    //                     // Create blob URL for the ZIP file
    //                     var blob = new Blob([data], {
    //                         type: 'application/zip'
    //                     });
    //                     var url = window.URL.createObjectURL(blob);

    //                     // Create a temporary link element
    //                     var link = document.createElement('a');
    //                     link.href = url;
    //                     link.download = 'LLaMediaDocument.zip'; // Specify the download filename
    //                     document.body.appendChild(link);
    //                     link.click();

    //                     // Cleanup
    //                     document.body.removeChild(link);
    //                     window.URL.revokeObjectURL(url);
    //                 },
    //                 error: function(xhr, status, error) {
    //                     console.error('Error generating zip file:', error);
    //                 }
    //             });
    //         },
    //         error: function(xhr, status, error) {
    //             console.error('Error fetching folder details:', error);
    //         }
    //     });
    // }

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
                        console.error('Error generating zip file:', error);
                        var errorMessage = xhr.responseJSON ? xhr.responseJSON.error :
                            'Error generating zip file';
                        alert(errorMessage);
                    }
                });
            },
            error: function(xhr, status, error) {
                console.error('Error generating zip file:', error);
                var errorMessage = xhr.responseJSON ? xhr.responseJSON.error :
                    'Error generating zip file';
                alert(errorMessage);
            }
        });
    }
</script>

<script>
    $(document).ready(function() {
        // Check All checkbox
        $('#checkAll').change(function() {
            $('input[name="folder_ids[]"]').prop('checked', $(this).prop('checked'));
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
        });
    });
</script>
