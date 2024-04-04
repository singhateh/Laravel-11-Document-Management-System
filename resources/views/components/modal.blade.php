@props([
    'modalId',
    'modalTitle' => 'Title',
    'onclick' => '#',
    'buttonText' => 'Save',
    'closeModal' => '',
    'size' => 'lg',
    'backDrop' => null,
    'modalFade' => 'fade',
    'onclickOnclose' => '',
])


<div class="modal {{ $modalFade }} custom-modal" id="{{ $modalId }}" tabindex="-1" role="dialog"
    aria-labelledby="{{ $modalId }}Label" aria-hidden="true" data-bs-backdrop="{{ $backDrop }}">
    <div class="modal-dialog custom-dialog modal-{{ $size }}" role="document">
        <div class="modal-content custom-content">
            <div class="modal-header custom-header p-1">
                <h5 class="modal-title custom-title" id="{{ $modalId }}Label">{{ $modalTitle }}</h5>
                <button type="button" class="close custom-close" onclick="{{ $closeModal }}()" data-bs-toggle="modal"
                    data-bs-target="#{{ $modalId }}" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body p-3">

                {{ $slot }}

            </div>
            <div class="modal-footer custom-footer p-2">
                <button type="submit" class="btn btn-primary custom-button"
                    onclick="{{ $onclick }}">{{ $buttonText }}</button>
                @if ($onclickOnclose)
                    <button type="submit" name="type" value="new" class="btn btn-primary custom-button"
                        onclick="saveFolderCategoryTag('new')">Save &
                        New</button>
                @endif
                <button type="button" onclick="{{ $closeModal }}()" data-bs-toggle="modal"
                    data-bs-target="#{{ $modalId }}" class="btn btn-secondary custom-button-close"
                    data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
