<x-modal modalId="requestDocumentModal" modalTitle="Request File" backDrop="static" buttonText="Request" onclick="submitRequestFormFolder()">
    <form id="requestFolderForm">
        <div class="error-message"></div>
        <!-- URL Input -->
        <label for="fileName" class="custom-label">Document Name</label>
        <div class="form-group col-md-6">
            <input type="text" autocomplete="false" class="form-control custom-input-lg col-md-6" id="requestFileName"
                name="name">
        </div>
        <!-- Name Input -->
        <div class="row">
            <div class="form-group">
                <label for="requestToInput" class="custom-label">Request To</label>
                <select name="request_to" id="requestToInput" class="form-control custom-input custom-select">
                    <option value=""></option>
                    @if (isset($owners))

                        @foreach ($owners as $owner)
                            <option value="{{ $owner->email }}">{{ $owner->name }}</option>
                        @endforeach
                    @endif

                </select>
            </div>
            <!-- Name Input -->
            <div class="form-group">
                <label for="requestFolderNameInput" class="custom-label">Folder Name</label>
                <select name="folder_id" id="requestFolderNameInput" class="form-control custom-input custom-select">
                    <option value=""></option>
                    {!! generateDropdownOptions() !!}
                </select>
            </div>
        </div>
        <div class="row">
            <div class="form-group">
                <label for="requestDueDateInNumberId" class="custom-label col-md-6">Due Date In</label>
                <div class="form-group">
                    <div class="col-md-6">
                        <input type="text" class="form-control custom-input col-md-6" id="requestDueDateInNumberId"
                            name="due_date_in_number">
                    </div>
                    <div class="col-md-6">
                        <select name="due_date_in_word" id="requestDueDateInWordInput"
                            class="form-control custom-input custom-select">
                            @foreach (getDueDateInList() as $day)
                                <option value="{{ $day }}">{{ $day }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
            <!-- Name Input -->
            <div class="form-group">
                <label for="requestFolderNameInput" class="custom-label">Tags</label>
                <select name="tag_id" id="requestTagNameInput" class="form-control custom-input custom-select">
                    <option value=""></option>
                    {!! generateCategoryTagsDropdownOptions() !!}
                </select>
            </div>
        </div>
        <!-- Visibility Checkbox -->
        <div class="form-group1">
            <label for="requestFolderNameInput" class="custom-label">Note</label>
            <div class="form-group">
                <textarea name="note" placeholder="Type / for comment" id="requestNoteId" cols="30" rows="10"
                    class="form-control custom-input "></textarea>
            </div>
        </div>
    </form>
</x-modal>

@once
    <script src="{{ asset('custom-js/request.js') }}"></script>
@endonce
