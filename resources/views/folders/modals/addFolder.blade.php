<x-modal modalId="createFolderModal" modalTitle="New Workspace" backDrop="static" modalFade=""
    onclick="saveFolderForm('{{ route('folders.store') }}')" closeModal="closeFolderModal">
    <form id="FolderCreateForm">
        <div class="error-message"></div>
        <!-- URL Input -->
        <label for="fileName" class="custom-label">Name</label>
        <div class="form-group col-md-6">
            <input type="text" autocomplete="false" placeholder="E.g Invoice"
                class="form-control custom-input-lg col-md-6" id="folderNameInput" name="folder_name">
        </div>
        <div class="form-group col-md-6">
            <label for="requestFolderNameInput" class="custom-label mt-3">Parent Workspaces</label>
            <select name="parent_id" id="folderParentNameInput" class="form-control custom-input custom-select">
                <option value=""></option>
                {!! generateDropdownOptions() !!}
            </select>
        </div>
        <div class="form-group1">
            <label for="requestFolderNameInput" class="custom-label">Tags</label>
            <div class="form-group1">
                <div id="renderFolderCategoryHtml">

                </div>
            </div>
            <div class="form-group">
                <a href="#" class="custom-a" onclick="FolderTagsModal()">Add Tag Line</a>
            </div>
        </div>
    </form>
</x-modal>


@once
    <script src="{{ asset('custom-js/addFolder.js') }}"></script>
@endonce
