<x-modal modalId="uploadFolderModal" backDrop="static" modalTitle="Upload Folder" buttonText="Upload"
    onclick="submitUploadFormFolder()">
    <form id="uploadFolderForm">
        <div class="error-message"></div>
        <!-- URL Input -->
        <div class="form-group">
            <label for="folderIdInput" class="custom-label mt-3">Parent Folder</label>
            <select name="folder_id" id="folderIdInput" class="form-control custom-input custom-select">
                <option value="">Parent</option>
                {!! generateDropdownOptions() !!}
            </select>

        </div>
        <!-- Name Input -->
        <div class="form-group">
            <label for="directoryInput" class="custom-label mt-3">Select Folder</label>
            <input type="file" webkitdirectory multiple class="form-control custom-input" id="directoryInput"
                name="files">
        </div>
        <!-- Name Input -->
        <div class="form-group">
            <label for="folderNameInput" class="custom-label mt-3">Folder Name</label>
            <input type="text" class="form-control custom-input" id="folderNameInput" name="folder_name">
        </div>
        <!-- Visibility Checkbox -->
        <div class="form-check mt-3">
            <input class="form-check-input custom-checkbox mt-2" type="checkbox" id="visibilityCheckbox"
                name="visibility" value="public" checked>
            <label class="form-check-label custom-check-label" for="visibilityCheckbox">
                Make private
            </label>
        </div>
    </form>
</x-modal>


@once
    <script src="{{ asset('custom-js/uploadFolder.js') }}"></script>
@endonce
