function submitUploadForm() {
    // Get form data
    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    const folderId = localStorage.getItem('selectedFolderId');
    var $form = $('#uploadForm'); // Get the current form

    var formData = {
        url: $('#urlAddmodalInput').val(),
        name: $('#urlnameInput').val(),
        visibility: $('#visibilityUrlCheckbox').is(':checked') ? 'private' : 'public',
        _token: csrfToken,
        folder_id: folderId
    };

    $.ajax({
        url: '/upload', // Replace with your server endpoint
        type: 'POST',
        data: formData,
        success: function (response) {
            fetchFiles(response.url);
            $('#uploadModal').modal('hide');
            $('#uploadForm')[0].reset();
        },
        error: function (xhr, status, error) {
            validation(xhr, $form);
        }
    });
}
