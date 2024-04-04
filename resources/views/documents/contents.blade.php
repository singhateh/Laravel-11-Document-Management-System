<div class="row" id="sortable">

    @forelse ($documents as $document)
        <div class="col-md-6 mb-3 documentContentClass " id="{{ $document->id }}">
            <div class="custom-card {{ $document->extension ? '' : 'bg-light-red' }}" draggable="true">

                <div class="{{ !in_array($document->extension, getImageExtensions()) ? 'img-container' : 'img-container-file' }}"
                    onclick="previewCourseFile('{{ $document->extension }}', '{{ asset($document->file_path) }}')"
                    data-previewFile="{{ $document->getFileIcon() }}">
                    <img src="{{ $document->getFileIcon() }}" width="0" height="0" alt=""
                        id="filePreviewId" />
                </div>

                <div class="card-content" onclick="selectCard(this)" data-id="{{ $document->id }}",
                    data-name="{{ $document->name }}" data-url="{{ $document->url }}"
                    data-path="{{ $document->file_path }}" data-folder="{{ $document->folder_id }}"
                    data-owner="{{ $document->owner }}" data-contact="{{ $document->contact }}"
                    data-img="{{ $document->getFileIcon() }}" data-visibility="{{ $document->isPublic() }}"
                    data-file_type="{{ $document->extension }}">
                    <div class="d-flex justify-content-between">
                        <div class="card-title">{{ $document->name }} <br>
                            @if ($document->tags)
                                @foreach ($document->tags as $tag)
                                    <a href="javascript:void()"> <small class="mt-0">{{ $tag->name }}</small></a>
                                @endforeach
                            @endif
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" class="card-radio" type="radio" id="radio1"
                                name="radio">
                            @if ($document->visibility === 'public')
                                <i class="fa fa-unlock mr-5" style="margin-right: 1rem"></i>
                            @else
                                <i class="fa fa-lock text-danger mr-5"></i>
                            @endif
                        </div>
                    </div>

                    <div class="date-emojis">
                        <div class="date">{{ $document->created_at?->format('d/m/Y') }}</div>
                        <div class="emojis"><i class="fa fa-clock"></i> <x-avatar width="20" height="20" /></div>
                    </div>
                </div>
            </div>
        </div>

    @empty
        <div class="col-md-6 mb-3 documentContentClass">
            <div class="no-document-found">
                <img src="{{ asset('img/empty-document.png') }}" alt="No Document Found Image">
                <p>No document found</p>
            </div>
        </div>
    @endforelse
</div>

<style>
    .custom-card>.img-container-file img {
        width: 70px;
        height: 70px;
        display: block;
        margin: 0 auto;
        object-fit: inherit;
    }

    .card-content {
        cursor: pointer;
    }
</style>

<style>
    .no-document-found {
        text-align: center;
        padding: 20px;
        margin: auto;
        background-color: whitesmoke;
        width: 60vw;
    }

    .no-document-found img {
        width: 200px;
        /* Adjust size as needed */
        height: auto;
        /* Maintain aspect ratio */
        margin-bottom: 20px;
    }

    .no-document-found p {
        font-size: 18px;
        color: #333;
        /* Adjust color as needed */
    }
</style>

<script>
    $(document).ready(function() {
        $("#sortable").sortable({
            containment: "parent", // Contain within the parent element
            cursor: "move",
            update: function(event, ui) {
                updateDocumentOrder();
            }
        });

        // Function to update document order via AJAX
        function updateDocumentOrder() {
            var documentIds = $("#sortable").sortable("toArray");
            var selectedFolderId = localStorage.getItem('selectedFolderId');

            // Send AJAX request to update document order
            $.ajax({
                url: '/update-document-order',
                type: 'POST',
                data: {
                    document_ids: documentIds,
                    folder_id: selectedFolderId
                },
                headers: {
                    'X-CSRF-TOKEN': csrfToken
                },
                success: function(response) {
                    console.log('Document order updated successfully.');
                },
                error: function(xhr, status, error) {
                    // Handle error
                    console.error('Error updating document order:', error);
                    alert('Error updating document order:', error)
                    // $('.errorMessage').html(error);
                }
            });
        }

    });
</script>
