    <div class="document-properties">
        <div class="card">
            <div class="card-header">
                <input type="hidden" name="" id="" class="previewFileExtension">
                <input type="hidden" name="" id="" class="previewFilePath">
                <img src="{{ asset('img/folder.png') }}" alt="" class="defaultImage" style="cursor: zoom-in"
                    onclick="previewDocumentImageFile(this)" id="imageInput">
                <i class="icon far fa-comments" onclick="addComment()"></i>
            </div>
            <div class="card-body folderProperty">
                <div id="renderFolderInfoHtml">
                </div>
            </div>
            <div class="card-body addOverlay1 fileProperty" style="display: none">
                <div class="d-flex justify-content-between">
                    <div class="btn-group share-buttons">
                        <i class="fa fa-download" id="download" title="Downlaod"></i>
                        <i class="fas fa-share-alt" id="share" title="Share"></i>
                        <i class="fas fa-retweet" id="change" title="Replace"
                            onclick="changeFileIcon(this, 'file')"></i>
                        <i class="fa fa-lock lock-icon" id="visibility" title="Lock" onclick="lockFile(this)"></i>
                        <input type="hidden" name="" id="isLockFile">
                    </div>
                    <div class="share-buttons">
                        <i class="fa fa-archive" title="Archive" onclick="changeFileIcon(this, 'archive')"></i>
                    </div>
                </div>
                <div class="mt-3">
                    <div class="row mb-3">
                        <label for="nameInput" class="col-sm-3 col-form-label">Name</label>
                        <div class="col-sm-9">
                            <input type="text" class="form-control" id="nameInput"
                                onkeydown="updateDocumentName(event, 'file_name')">
                        </div>
                    </div>
                    <div class="row mb-3">
                        <label for="urlInput" class="col-sm-3 col-form-label">URL</label>
                        <div class="col-sm-9">
                            <input type="url" class="form-control" id="urlInput">
                        </div>
                    </div>
                    <div class="row mb-3">
                        <label for="contactSelect" class="col-sm-3 col-form-label">Contact</label>
                        <div class="col-sm-9">
                            <select class="form-select" id="contactSelect">
                                <option value="email">Email</option>
                                <option value="phone">Phone</option>
                            </select>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <label for="ownerSelect" class="col-sm-3 col-form-label"><i class="fa fa-user"></i>
                            Owner</label>
                        <div class="col-sm-9">
                            <select class="form-select" id="ownerSelect" onchange="changeFileIcon(this, 'owner')">
                                @foreach ($owners as $owner)
                                    <option value="{{ $owner->id }}">{{ $owner->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <label for="folderSelected" class="col-sm-3 col-form-label"><i class="fa fa-folder"></i>
                            Folder</label>
                        <div class="col-sm-9">
                            <select class="form-select" id="folderSelected" onchange="changeFileIcon(this, 'folder')">
                                {!! generateDropdownOptions() !!}
                            </select>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <label for="tagsInput" class="col-sm-3 col-form-label"><i class="fa fa-tag"></i> Tags</label>
                        <div class="col-sm-9">
                            <select class="form-select" id="folderSelected" onchange="addTag(this, 'folder')">
                                {!! generateCategoryTagsDropdownOptions() !!}
                            </select>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <style>
        #tagsInput {
            width: 100%;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 5px;
            outline: none;
        }

        #tagsInput::placeholder {
            color: #999;
            /* Placeholder text color */
        }

        .category-tags {
            font-size: 14px;
            line-height: 20px;
        }

        .category label {
            font-size: 5px;
            /* margin-bottom: 10px; */
        }
    </style>

    <script>
        // Function to handle input change and perform search
        function searchTag() {
            $('#tagsInput').on('input', function() {
                const searchQuery = $(this).val().trim();
                if (searchQuery.length >= 2) { // Perform search if query is at least 2 characters long
                    $.ajax({
                        url: '/search-tags', // Replace with your server endpoint for searching tags
                        method: 'GET',
                        data: {
                            query: searchQuery
                        },
                        success: function(response) {
                            displayTagSuggestions(response.tags); // Update UI with tag suggestions
                        },
                        error: function(xhr, status, error) {
                            console.error('Error searching tags:', error);
                        }
                    });
                }
            });
        }

        // Function to display tag suggestions
        function displayTagSuggestions(tags) {
            const suggestionsDiv = $('#tagSuggestions');
            suggestionsDiv.empty(); // Clear previous suggestions
            tags.forEach(tag => {
                const tagElement = $('<div>').text(tag.name).addClass('tag-suggestion');
                tagElement.on('click', function() {
                    addTag(tag.id, tag.name); // Add the clicked tag
                });
                suggestionsDiv.append(tagElement);
            });
        }

        // Function to add a tag
        function addTag(tagId, tagName) {
            // Send AJAX request to add the tag
            $.ajax({
                url: '/add-tag', // Replace with your server endpoint for adding tags
                method: 'POST',
                data: {
                    tag_id: tagId
                },
                success: function(response) {
                    console.log('Tag added successfully:', tagName);
                    $('#tagsInput').val(''); // Clear the input field after adding the tag
                },
                error: function(xhr, status, error) {
                    console.error('Error adding tag:', error);
                }
            });
        }
    </script>

    <script>
        function handleCategoryCheckboxChange() {
            // Handle category checkbox change event
            $(document).on('change', '.category-checkbox', function() {
                var categoryId = $(this).val();
                $('.tag-checkbox-' + categoryId).prop('checked', $(this).prop('checked'));
                checkCategoryCheckboxStatus(categoryId);
            });

        }

        function handleTagCheckboxChange() {
            // Handle tag checkbox change event
            $(document).on('change', '.tag-checkbox', function() {
                var categoryId = $(this).data('category-id');
                var checkedTagsCount = $('.tag-checkbox-' + categoryId + ':checked').length;
                var totalTagsCount = $('.tag-checkbox-' + categoryId).length;

                $('.category-checkbox[value="' + categoryId + '"]').prop('checked', checkedTagsCount ===
                    totalTagsCount);
                checkCategoryCheckboxStatus(categoryId);
            });

            filterDocuments();
        }

        function checkCategoryCheckboxStatus(categoryId) {
            // Check if all tag checkboxes are checked for the category and update the category checkbox accordingly
            var checkedTagCheckboxes = $('.tag-checkbox-' + categoryId + ':checked').length;
            var totalTagCheckboxes = $('.tag-checkbox-' + categoryId).length;

            if (checkedTagCheckboxes === totalTagCheckboxes) {
                $('.category-checkbox[value="' + categoryId + '"]').prop('checked', true);
            } else {
                $('.category-checkbox[value="' + categoryId + '"]').prop('checked', false);
            }

        }


        function filterDocuments() {
            // Get selected tag IDs
            var selectedTags = [];
            var folder = localStorage.getItem('selectedFolderId');
            $('.tags-tosend:checked').each(function() {
                selectedTags.push($(this).val());
            });

            localStorage.setItem('selectedFolderTags', selectedTags);

            // Send AJAX request to filter documents based on selected tags
            $.ajax({
                url: '/filter-documents-by-tags',
                type: 'GET',
                data: {
                    tags: selectedTags,
                    folder: folder,
                },
                success: function(response) {
                    $('#renderDocumentContentHtml').html(response.html);
                },
                error: function(xhr, status, error) {
                    console.error('Error filtering documents:', error);
                }
            });
        }
    </script>
