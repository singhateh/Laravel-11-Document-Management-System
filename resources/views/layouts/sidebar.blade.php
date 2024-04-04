<div class="sidebar">
    <div class="folder-structure">
        <h4 class="mr-3"> <i class="fas fa-folder folder-icon"></i> Workspace</h4>
        <hr>

        <ul class="folders">
            @if (isset($folders))
                {!! $folders !!}
            @else
                {!! generateSidebarMenu() !!}
            @endif

        </ul>

        <div id="renderFolderTagsHtml"></div>
    </div>
</div>

<style>
    .folder-item {
        position: relative;
    }

    .folder-content {
        display: flex;
        align-items: center;
    }

    .toggle-subfolders-btn {
        position: absolute;
        right: 0;
        transform: translateY(-50%);
        background: none;
        border: none;
        cursor: pointer;
        margin-top: -0.6rem;
        color: white;
        padding-right: 4px;
    }

    .subfolders {
        margin-left: 20px;
    }

    .category {
        margin-left: 0.5rem;
        margin-bottom: 3px;
    }

    .category-tags {
        margin-left: 1rem;
        display: block;
    }

    .tags hr {
        margin: 0.5em;
    }
</style>
