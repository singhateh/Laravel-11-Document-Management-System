// Initialize variables
var visibilityElement = document.getElementById('visibility');
var isGlobalPublic;
var isCardSelected;
var dataId;
var previewFileData;
var csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
var selectedCardId;

// Function to check if a file type is an image
function isImageFileType(fileType) {
    const imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'svg', 'tif', 'tiff'];
    const lowerCaseFileType = fileType.toLowerCase(); // Convert to lowercase for case-insensitive comparison
    return imageExtensions.includes(lowerCaseFileType);
}

// Document ready event handler
$(document).ready(function () {
    var selectedFolder = localStorage.getItem('selectedFolder');

    if (selectedFolder) {
        fetchFiles(selectedFolder, 'folder');
    } else {
        var defaultFolder = $('.folders li:first-child a').attr('href');
        fetchFiles(defaultFolder, 'folder');
    }

    // reCheckedTags();
});

function reCheckedTags() {

    var selectedTagsString = localStorage.getItem('selectedFolderTags');
    if (selectedTagsString) {
        var selectedTagsArray = selectedTagsString.split(',');

        setTimeout(function () {
            $('.tags-tosend').each(function () {
                if (selectedTagsArray.includes($(this).val())) {
                    $(this).prop('checked', true);
                }
            });
        }, 1000);
    }
}

function getSelectedTags() {

    var selectedTagsString = localStorage.getItem('selectedFolderTags');
    if (selectedTagsString) {
        return selectedTagsString.split(',');
    }
}



function fetchFiles(url, folder = null) {


    // Remove active class from all folders
    $('.folders li a').removeClass('active');
    closeComment();

    // Find the folder with the matching href attribute
    var folderLink = $('.folders li a[data-url="' + url + '"]');
    var folderName = folderLink.attr('data-folder');

    if (folderLink.length) {
        // Add active class to the selected folder
        folderLink.addClass('active');
    }

    // Save selected folder to local storage
    localStorage.setItem('selectedFolder', url);
    localStorage.setItem('selectedShareDocumentFolder', folderName);

    // Parse the JSON string into an array
    var selectedTags = getSelectedTags();

    reCheckedTags();

    if (folder !== null) {
        // Change document properties style to 'card-header-info'
        $('.document-properties').find('.card-header-img, .card-header').removeClass(
            'card-header-img card-header').addClass('card-header-info');

        // Update the image to a folder image
        $('#imageInput').attr('src', $('#getFolderIcon').attr('src'));
        $('.folderProperty').show();
        $('.fileProperty').hide();

        localStorage.setItem('selectedFolderTags', '');
        localStorage.setItem('selectedDocumentId', '');

    } else {
        $('.folderProperty').hide();
        $('.fileProperty').show();
        if (selectedCardId) {
            var selectedCard = document.querySelector('.card-content[data-id="' + selectedCardId +
                '"]');
            if (selectedCard) {
                selectedCard.classList.add('selected'); // Add 'selected' class to the selected card

                // Find the radio button within the selected card
                var radioButton = selectedCard.querySelector('input[type="radio"]');
                if (radioButton) {
                    radioButton.checked = true; // Check the radio button
                }
            }
        }
    }

    // AJAX request to fetch files
    $.ajax({
        url: url,
        method: 'GET',
        data: {
            tags: selectedTags,
        },
        beforeSend: function () {
            // Show loading overlay
            $('#loadingOverlay').show();
        },
        complete: function () {
            $('#loadingOverlay').hide();
            $('.page-content').show();
        },
        success: function (response) {
            // Render document content
            $('#renderDocumentContentHtml').html(response.html);
            // Render folder info
            $('#renderFolderInfoHtml').html(response.folderInfoHml);
            $('#renderFolderTagsHtml').html(response.categoriesHtml);
            if (folder === null) {
                $('#renderSidebarHtmlId').html(response.folderHtml);
            }

            localStorage.setItem('selectedFolderId', response.folder_id);

        },
        error: function (xhr, status, error) {
            // Handle error
            console.error('Error fetching files:', error);
            $('.errorMessage').html(error);
        }
    });
}


// Function to handle card selection
function selectCard(card) {
    // Extract data from selected card
    dataId = card.dataset.id;
    const dataName = card.dataset.name;
    const dataUrl = card.dataset.url;
    const dataPath = card.dataset.path;
    const dataImgUrl = card.dataset.img;
    const dataFolder = card.dataset.folder;
    const dataOwner = card.dataset.owner;
    const dataContact = card.dataset.contact;
    const isPublic = card.dataset.visibility;
    const fileType = card.dataset.file_type;

    $('.previewFileExtension').val(fileType);
    $('.previewFilePath').val(dataImgUrl);

    localStorage.setItem('selectedShareDocumentFolder', dataName);


    // Update global variables
    isGlobalPublic = $('#isLockFile').val(isPublic);
    isCardSelected = card;
    selectedCardId = card.dataset.id;

    // Populate input fields with extracted data
    const imageInput = document.getElementById('imageInput');
    imageInput.src = dataImgUrl;
    imageInput.setAttribute('data-preview', dataPath);
    document.getElementById('nameInput').value = dataName;
    document.getElementById('urlInput').value = dataUrl;
    document.getElementById('contactSelect').value = dataContact;
    document.getElementById('ownerSelect').value = dataOwner;
    document.getElementById('folderSelected').value = dataFolder;

    // Update visibility icon based on public/private status
    if (isPublic === 'private') {
        visibilityElement.classList.remove('fa-unlock');
        visibilityElement.classList.add('fa-lock', 'bg-danger');
        $('#isLockFile').val('private');
    } else {
        visibilityElement.classList.remove('fa-lock', 'bg-danger');
        visibilityElement.classList.add('fa-unlock');
        $('#isLockFile').val('public');
    }


    // Check file type and update card header styling accordingly
    if (!isImageFileType(fileType)) {
        $('.document-properties').find('.card-header-img, .card-header-info').removeClass(
            'card-header-img card-header-info defaultImage').addClass('card-header');
        $('.document-properties').find('.defaultImage').removeClass(
            'defaultImage');
        $('#change').hide();
    } else {
        $('.document-properties').find('.card-header, .card-header-info').removeClass(
            'card-header card-header-info').addClass('card-header-img');
        $('#change').show();
    }

    $('.folderProperty').hide();
    $('.fileProperty').show();

    // Clear previous tags
    // const tagsInput = document.getElementById('tagsInput');
    // tagsInput.value = '';

    // Deselect all cards and select the clicked card
    const cards = document.querySelectorAll('.card-content');
    cards.forEach(c => {
        c.classList.remove('selected');
        const radioButton = c.querySelector('input[type="radio"]');
        if (radioButton) {
            radioButton.checked = false;
        }
    });

    card.classList.add('selected');
    $('.document-properties').find('.card-body').removeClass('addOverlay');

    localStorage.setItem('selectedDocumentId', dataId);

    // Check radio button inside the clicked card
    const radioButton = card.querySelector('input[type="radio"]');
    // alert(radioButton)
    if (radioButton) {
        radioButton.checked = true;
    }

    getDocumentCommentAjax(dataId);
}


function changeFileIcon(element, type) {

    var value = element.value;

    // alert(value); return false;

    var isGlobalPublic = $('#isLockFile').val();

    // Prevent file icon change if file is private
    if (isGlobalPublic === 'private') return false;

    if (type === 'file') {
        // Create file input element and trigger file selection
        const fileInput = document.createElement('input');
        fileInput.type = 'file';
        fileInput.style.display = 'none';
        fileInput.addEventListener('change', function () {
            const file = fileInput.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function (event) {
                    document.getElementById('imageInput').src = event.target.result;
                };
                reader.readAsDataURL(file); // Read the file as a data URL

                // Prepare data to send via AJAX
                const formData = new FormData();
                formData.append('file', file);
                formData.append('type', type);
                formData.append('folder_id', localStorage.getItem('selectedFolderId'));
                formData.append('document_id', localStorage.getItem('selectedDocumentId'));

                // Send AJAX request
                sendServerAjaxForDocumentProperty(formData);
            } else {
                document.getElementById('imageInput').src = event.target.result;
            }

            $('.document-properties').find('.card-header').removeClass('card-header').addClass('card-header-img');
        });
        document.body.appendChild(fileInput);
        fileInput.click();
    } else if (type === 'folder') {
        localStorage.setItem('selectedFolderId', value);
        const formData = new FormData();
        formData.append('type', type);
        // formData.append('folder', value);
        formData.append('folder_id', localStorage.getItem('selectedFolderId'));
        formData.append('document_id', localStorage.getItem('selectedDocumentId'));

        // Send AJAX request
        sendServerAjaxForDocumentProperty(formData);
    } else if (type === 'owner') {
        const formData = new FormData();
        formData.append('type', type);
        formData.append('data', value);
        formData.append('folder_id', localStorage.getItem('selectedFolderId'));
        formData.append('document_id', localStorage.getItem('selectedDocumentId'));

        // Send AJAX request
        sendServerAjaxForDocumentProperty(formData);
    } else if (type === 'archive') {
        const formData = new FormData();
        formData.append('type', type);
        formData.append('data', value);
        formData.append('folder_id', localStorage.getItem('selectedFolderId'));
        formData.append('document_id', localStorage.getItem('selectedDocumentId'));

        // Send AJAX request
        sendServerAjaxForDocumentProperty(formData);
    }
}


function updateDocumentName(event, type) {
    if (event.keyCode === 13) { // Check if Enter key is pressed
        event.preventDefault(); // Prevent default form submission
        var value = event.target.value;

        const formData = new FormData();
        formData.append('type', type);
        formData.append('data', value);
        formData.append('folder_id', localStorage.getItem('selectedFolderId'));
        formData.append('document_id', localStorage.getItem('selectedDocumentId'));

        // Send AJAX request
        sendServerAjaxForDocumentProperty(formData);
    }
}


function sendServerAjaxForDocumentProperty(formData) {

    $.ajax({
        url: '/change-document', // Replace with your endpoint
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        headers: {
            'X-CSRF-TOKEN': csrfToken
        },
        success: function (response) {
            fetchFiles(response.url);
        },
        error: function (xhr, status, error) {
            console.error('Error changing file icon:', error);
            // Handle error response if needed
        }
    });
}

// Function to lock/unlock file
function lockFile(card) {
    var isGlobalPublic = $('#isLockFile').val();

    // Toggle lock/unlock icon and update global variable
    if (isGlobalPublic === 'private') {
        $(card).removeClass('fa-lock bg-danger').addClass('fa-unlock');
        $('#isLockFile').val('public');
        updateVisibility(card, dataId, isGlobalPublic);
    } else {
        $(card).removeClass('fa-unlock').addClass('fa-lock bg-danger');
        $('#isLockFile').val('private');
        updateVisibility(card, dataId, isGlobalPublic);

    }
}

// Function to update file visibility
function updateVisibility(card, documentId, visibility) {

    // Parse the JSON string into an array
    var selectedTags = getSelectedTags();

    $.ajax({
        url: "/update-visibility",
        type: "POST",
        data: {
            _token: csrfToken,
            document_id: documentId,
            visibility: visibility,
            tags: selectedTags,
        },
        success: function (response) {

            // alert(response.url)
            $('#loadingOverlay').hide();
            fetchFiles(response.url);

            // Select the previously selected card
            if (selectedCardId) {
                var selectedCard = document.querySelector('.card-content[data-id="' + selectedCardId +
                    '"]');
                if (selectedCard) {
                    selectedCard.classList.add('selected'); // Add 'selected' class to the selected card

                    // Find the radio button within the selected card
                    var radioButton = selectedCard.querySelector('input[type="radio"]');
                    if (radioButton) {
                        radioButton.checked = true; // Check the radio button
                    }
                }
            }

            isGlobalPublic = $('#isLockFile').val(response.visibility);
        },
        error: function (xhr, status, error) {
            console.error(xhr.responseText); // Log any errors
        }
    });
}


function getDocumentCommentAjax(documentId) {

    $.ajax({
        url: "/getDocumentComments",
        type: "GET",
        data: {
            document_id: documentId,
        },
        success: function (response) {
            $('#renderDocumentCommentHtml').html(response.html)
        },
        error: function (xhr, status, error) {
            console.error(xhr.responseText); // Log any errors
        }
    });
}

function previewCourseFile(fileType, fileUrl) {
    openPreviewModal(fileType, fileUrl);
    // document.querySelector('.overlay').style.display = 'block';
}

function addComment() {
    // Adjust classes for the first element
    $('#documentContent').removeClass('col-md-9').addClass('col-md-5');
    $('.documentContentClass').removeClass('col-md-6').addClass('col-md-12');
    $('#documentCommentSection').show();
    $('.icon').hide();

    // Adjust classes for the second element
    $('#documentProperty').removeClass('col-md-3').addClass('col-md-7');
    $('#documentProperty').find('.col-md-12').removeClass('col-md-12').addClass('col-md-5');
}

function closeComment(params) {
    $('#documentContent').removeClass('col-md-5').addClass('col-md-9');
    $('.documentContentClass').removeClass('col-md-12').addClass('col-md-6');
    $('#documentCommentSection').hide();
    $('.icon').show();

    // Adjust classes for the second element
    $('#documentProperty').removeClass('col-md-7').addClass('col-md-3');
    $('#documentProperty').find('.col-md-5').removeClass('col-md-5').addClass('col-md-12');
}


// Function to open file preview modal
function openPreviewModal(fileType, fileUrl) {
    // Check the file type and display appropriate preview content
    if (['mp4', 'webm', 'ogg', 'avi', 'mov', 'wmv', 'flv', 'mkv'].includes(fileType)) {
        // Display video preview using iframe or video tag
        $('#previewContent').html('<video src="' + fileUrl + '" controls></video>');
    } else if (['mp3', 'wav', 'ogg', 'aac', 'flac', 'wma', 'm4a', 'opus'].includes(fileType)) {
        // Display audio preview using audio tag
        $('#previewContent').html('<audio src="' + fileUrl + '" controls class="custom-audio"></audio>');
    } else if (['ppt', 'pptx', 'pps', 'ppsx'].includes(fileType)) {
        // Display presentation preview using iframe or embed tag
        $('#previewContent').html(
            `<iframe src="https://docs.google.com/viewer?url=${fileUrl}&embedded=true"></iframe>`);
    } else if (['pdf', 'PDF', 'pdfx'].includes(fileType)) {
        // Display PDF preview using iframe or embed tag
        $('#previewContent').html('<iframe src="' + fileUrl + '"></iframe>');
    } else if (['doc', 'docx'].includes(fileType)) {
        // Display Word document preview using iframe or embed tag
        $('#previewContent').html('<iframe src="' + fileUrl + '"></iframe>');
    } else if (['jpg', 'jpeg', 'png', 'gif', 'bmp', 'svg'].includes(fileType)) {
        // Display image preview
        $('#previewContent').html('<img src="' + fileUrl +
            '" style="width: 100%; height: 100%; object-fit: contain;">');
    } else {
        // Display YouTube video preview
        var videoId = extractYouTubeVideoId(fileUrl);
        const embedUrl = 'https://www.youtube.com/embed/' + videoId + '?rel=0';
        $('#previewContent').html('<iframe src="' + embedUrl + '"></iframe>');
    }

    // Open the preview modal
    $('#previewModal').modal('show');
}

// Function to extract YouTube video ID from URL
function extractYouTubeVideoId(url) {
    const match = url.match(
        /(?:https?:\/\/)?(?:www\.)?(?:youtube\.com\/(?:[^\/\n\s]+\/\S+\/|(?:v|e(?:mbed)?)\/|\S*?[?&]v=)|youtu\.be\/)([a-zA-Z0-9_-]{11})/
    );
    return match ? match[1] : null;
}



// Tabs Script Section
const tabButtons = document.querySelectorAll('.tab-btn')

tabButtons.forEach((tab) => {
    tab.addEventListener('click', () => tabClicked(tab))
})

function tabClicked(tab) {

    tabButtons.forEach(tab => {
        tab.classList.remove('active')
    })
    tab.classList.add('active')

    const contents = document.querySelectorAll('.tab-content')

    contents.forEach((content) => {
        content.classList.remove('show')
    })

    const contentId = tab.getAttribute('content-id')
    const contentSelected = document.getElementById(contentId)

    contentSelected.classList.add('show')
    //console.log(contentId)
}


// SEARCH SECTION

const messageInputs = document.querySelectorAll('.messageInput');
const userDropdowns = document.querySelectorAll('.userDropdown');
let selectedUsers = [];

// Define an asynchronous function to handle input events
async function handleInputEvent(event) {
    const text = event.target.value;
    const matches = text.match(/\@\w+/g); // Regex to match all occurrences of "@username"
    if (matches && text != '') {
        const usernames = matches.map(match => match.substring(1)); // Extract usernames
        const newUsers = usernames.filter(username => !selectedUsers.includes(username));
        if (newUsers.length > 0 && matches.length === 1) {
            const users = await searchUsers(newUsers); // Call backend API to search for new users
            renderUserDropdown(users);
            $('.selectedUsersId').val('');
        }
    } else {
        hideUserDropdown();
    }
}


async function searchUsers(usernames) {
    const response = await fetch(`/api/users?q=${usernames.join(',')}`);
    const data = await response.json();
    return data.users;
}

function renderUserDropdown(users) {
    // Render the dropdown with user suggestions
    userDropdown.innerHTML = '';
    users.forEach(user => {
        const option = document.createElement('div');
        option.textContent = `${user.name} (${user.email})`;
        option.addEventListener('click', () => {
            appendUsername(user.name, user.email);
            hideUserDropdown();
        });
        userDropdown.appendChild(option);
    });
    userDropdown.style.display = 'block';
}

function appendUsername(username, email) {
    // Append selected username to the textarea
    selectedUsers.push(username);

    // Append selected username to the textarea
    const currentText = messageInput.value;
    const newText = currentText.replace(/@\w+/g, `@${username} `);
    messageInput.value = newText;
    $('#selectedUsersId').val(email);
    messageInput.focus();
}


function hideUserDropdown() {
    userDropdown.style.display = 'none';
}

const getMessages = document.querySelectorAll('.message');

// Loop through each message element
getMessages.forEach(message => {
    // Get the text content of the message
    const content = message.textContent;

    // Use regular expression to find all words that start with "@"
    const regex = /@\w+/g;
    const coloredContent = content.replace(regex, '<strong class="highlight">$&</strong>');

    // Set the modified content back to the message element
    message.innerHTML = coloredContent;
});