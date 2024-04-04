<x-modal modalId="uploadModal" modalTitle="Add Url" backDrop="static" buttonText="Add" onclick="submitUploadForm()">
    <form id="uploadForm">
        <div class="error-message"></div>
        <!-- URL Input -->
        <div class="form-group">
            <label for="urlAddmodalInput" class="custom-label">URL</label>
            <input type="text" class="form-control custom-input" id="urlAddmodalInput" name="url"
                placeholder="Enter URL">
        </div>
        <!-- Name Input -->
        <div class="form-group">
            <label for="urlnameInput" class="custom-label">Name</label>
            <input type="text" class="form-control custom-input" id="urlnameInput" name="name"
                placeholder="Enter Name">
        </div>
        <!-- Visibility Checkbox -->
        <div class="form-check">
            <input class="form-check-input custom-checkbox" type="checkbox" id="visibilityUrlCheckbox" name="visibility"
                value="public" checked>
            <label class="form-check-label custom-check-label" for="visibilityUrlCheckbox">
                Make private
            </label>
        </div>
    </form>
</x-modal>

@once
    <script src="{{ asset('custom-js/addUrl.js') }}"></script>
@endonce
