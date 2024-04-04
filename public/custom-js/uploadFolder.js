
document.getElementById('directoryInput').addEventListener('change', function (event) {
    const directory = event.target.files[0];
    const directoryPath = directory.webkitRelativePath; // Get the path of any file in the directory
    const directoryName = directoryPath.split('/')[0]; // Extract the directory name from the path

    $('#folderNameInput').val(directoryName);
    console.log('Selected directory name:', directoryName);
});


function submitUploadFormFolder() {
    // Get form data
    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    const folderId = $('#folderIdInput').val();
    const folderName = $('#folderNameInput').val();
    const files = $('#directoryInput').prop('files');
    const visibility = $('#visibilityCheckbox').is(':checked') ? 'private' : 'public';
    var $form = $('#uploadFolderForm'); // Get the current form

    var formData = new FormData();
    formData.append('folder_name', folderName);
    formData.append('files[]', []);
    for (let i = 0; i < files.length; i++) {
        formData.append('files[]', files[i]);
    }
    formData.append('visibility', visibility);
    formData.append('_token', csrfToken);
    formData.append('folder_id', folderId);

    $.ajax({
        url: '/upload', // Replace with your server endpoint
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function (response) {
            fetchFiles(response.url);
            $('#uploadFolderModal').modal('hide');
            $('#uploadFolderForm')[0].reset();
        },
        error: function (xhr, status, error) {
            validation(xhr, $form);
        }
    });
}
