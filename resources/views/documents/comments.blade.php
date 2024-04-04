<div class=" commentContent">

    <div class="card-header d-flex justify-content-between">
        <div class="tab-buttons">
            <button class="tab-btn active1" onclick="tabClicked(this)" content-id="home" title="Send Message">
                Message
            </button>
            <button class="tab-btn" onclick="tabClicked(this)" content-id="services" title="Log Note">
                Log note
            </button>
            {{-- <button class="tab-btn" onclick="tabClicked(this)" content-id="contact" title="Schedule Activity">
                Schedule
            </button> --}}
        </div>
        <i class="fa fa-times close-icon" onclick="closeComment()"></i>
    </div>

    <div class="tab-contents">
        <div class="tab-content show1" id="home">
            <div class1="content-info">
                <h1 class="content-title">
                    <x-avatar width="30" height="30" />
                </h1>
                <div class="content-description1">
                    <form id="sendDocumentFormId">
                        <input type="hidden" name="type" value="message">
                        <textarea class="custom-textarea" name="content" oninput="handleInputEvent(event)" id="messageInput"
                            placeholder="Type your message here..."></textarea>
                        <div id="userDropdown" class="userDropdown"></div>
                        <input type="hidden" name="user_email" class="form-control col-md-12 selectedUsersId"
                            id="selectedUsersId">
                        <button type="button" class="btn btn-sm btn-info"
                            onclick="sendEmail('sendDocumentFormId')">Send</button>
                    </form>
                </div>
            </div>
            {{-- <hr> --}}
        </div>
        <div class="tab-content" id="services">
            <div class="content-info">
                <h1 class="content-title">
                    <x-avatar width="30" height="30" />
                </h1>
                <div class="content-description1">
                    <form id="sendDocumentLogFormId">
                        <input type="hidden" name="type" value="log">
                        <textarea class="custom-textarea" name="content" oninput1="handleInputEvent(event)" id="messageLogInput"
                            placeholder="Type your log here..."></textarea>
                        {{-- <div id="userDropdown" class="userDropdown"></div> --}}
                        <input type="hidden" name="user_email" class="form-control col-md-12 selectedUsersId"
                            id="selectedUsersId">
                        <button type="button" class="btn btn-sm btn-info"
                            onclick="sendEmail('sendDocumentLogFormId')">Send</button>
                    </form>
                </div>
            </div>
        </div>
        {{-- <div class="tab-content" id="contact">
            <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="200"
                height="300" viewBox="0 0 655.86 506.44" id="currentIllo"
                class="injected-svg DownloadModal__ImageFile-sc-p17csy-5 iIfSkb grid_media">
                <path
                    d="m350.22,378.66c-1.77,10.71-11.09,18.91-22.29,18.91s-20.53-8.2-22.29-18.91h-57.04v123.61h158.66v-123.61h-57.04,0Z"
                    fill="#dfdfdf" stroke-width="0"></path>
                <rect x="251.36" y="501.81" width="15.68" height="2.77" fill="#bcb9cb" stroke-width="0"></rect>
                <rect x="390.19" y="502.27" width="15.68" height="2.77" fill="#bcb9cb" stroke-width="0"></rect>
                <rect x="249.06" y="492.47" width="158.66" height="4.3" fill="#2f2e41" stroke-width="0"></rect>
                <path
                    d="m647.01,381.89H8.85c-4.88,0-8.85-3.97-8.85-8.85V8.85C0,3.97,3.97,0,8.85,0h638.16c4.88,0,8.85,3.97,8.85,8.85v364.2c0,4.88-3.97,8.85-8.85,8.85h0Z"
                    fill="#2f2e41" stroke-width="0"></path>
                <rect x="16.14" y="15.68" width="624.49" height="352.37" fill="#fff" stroke-width="0"></rect>
                <path
                    d="m328.75,129.57h-38.41c-12.07,0-21.89-9.82-21.89-21.89s9.82-21.89,21.89-21.89h38.41c12.07,0,21.89,9.82,21.89,21.89s-9.82,21.89-21.89,21.89Z"
                    fill="#dfdfdf" stroke-width="0"></path>
                <circle cx="291.11" cy="107.68" r="16.9" fill="#fff" stroke-width="0"></circle>
                <path
                    d="m328.75,280.44h-38.41c-12.07,0-21.89-9.82-21.89-21.89s9.82-21.89,21.89-21.89h38.41c12.07,0,21.89,9.82,21.89,21.89s-9.82,21.89-21.89,21.89Z"
                    fill="#dfdfdf" stroke-width="0"></path>
                <circle cx="291.11" cy="258.55" r="16.9" fill="#fff" stroke-width="0"></circle>
                <path
                    d="m328.75,204.86h-38.41c-12.07,0-21.89-9.82-21.89-21.89s9.82-21.89,21.89-21.89h38.41c12.07,0,21.89,9.82,21.89,21.89s-9.82,21.89-21.89,21.89Z"
                    fill="#dfdfdf" stroke-width="0"></path>
                <circle cx="327.56" cy="182.97" r="16.9" fill="#6c63ff" stroke-width="0"></circle>
                <path
                    d="m149,505.53c0,.5.4.91.91.91h356.03c.5,0,.91-.4.91-.91s-.4-.91-.91-.91H149.91c-.5,0-.91.4-.91.91Z"
                    fill="#2f2e43" stroke-width="0"></path>
                <polygon points="398.94 198.22 382 203.79 382 179.4 397.37 179.4 398.94 198.22" fill="#f2a7aa"
                    stroke-width="0"></polygon>
                <circle cx="384.1" cy="169.27" r="16.88" fill="#f2a7aa" stroke-width="0"></circle>
                <path
                    d="m388.73,167.6c-2.83-.09-4.68-2.94-5.78-5.55s-2.23-5.6-4.85-6.68c-2.14-.88-5.92,5.07-7.62,3.48-1.76-1.65-.04-10.13,1.83-11.66s4.43-1.82,6.84-1.93c5.88-.27,11.8.2,17.57,1.41,3.57.74,7.24,1.86,9.81,4.44,3.26,3.27,4.09,8.21,4.33,12.82.24,4.72-.03,9.66-2.33,13.79-2.3,4.13-7.1,7.18-11.7,6.12-.46-2.5,0-5.07.19-7.61.18-2.54,0-5.28-1.56-7.31s-4.86-2.82-6.67-1.03"
                    fill="#36344e" stroke-width="0"></path>
                <path
                    d="m409.5,173.45c1.69-1.24,3.71-2.27,5.79-2.02,2.25.27,4.14,2.12,4.72,4.31s-.07,4.6-1.46,6.39c-1.39,1.79-3.45,2.97-5.64,3.56-1.26.34-2.65.48-3.85-.03-1.78-.76-2.73-3.03-2.04-4.84"
                    fill="#36344e" stroke-width="0"></path>
                <rect x="377.48" y="469.58" width="15.87" height="22.51" fill="#f2a7aa" stroke-width="0"></rect>
                <path
                    d="m362.02,505.72c-1.67,0-3.15-.04-4.27-.14-4.22-.39-8.24-3.5-10.26-5.32-.91-.82-1.2-2.12-.73-3.24h0c.34-.81,1.02-1.41,1.86-1.65l11.14-3.18,18.04-12.17.2.36c.08.13,1.85,3.32,2.44,5.48.23.82.17,1.5-.18,2.03-.24.37-.57.58-.84.7.33.34,1.35,1.03,4.5,1.54,4.6.73,5.57-4.04,5.61-4.24l.03-.16.14-.09c2.19-1.41,3.54-2.05,4-1.91.29.09.78.23,2.08,13.22.13.41,1.05,3.4.42,6.25-.68,3.11-14.26,2.04-16.97,1.79-.08,0-10.25.73-17.21.73h0Z"
                    fill="#36344e" stroke-width="0"></path>
                <rect x="429.82" y="454.38" width="15.87" height="22.51"
                    transform="translate(-180.09 302.19) rotate(-31.95)" fill="#f2a7aa" stroke-width="0"></rect>
                <path
                    d="m420.65,502.63c-1.86,0-3.57-.22-4.79-.44-1.2-.21-2.14-1.16-2.33-2.37h0c-.14-.86.12-1.73.7-2.38l7.77-8.59,8.86-19.87.36.2c.14.07,3.33,1.84,4.97,3.36.63.58.94,1.19.93,1.82,0,.44-.17.79-.34,1.04.46.12,1.69.16,4.63-1.08,4.29-1.81,2.59-6.37,2.51-6.56l-.06-.15.07-.15c1.11-2.36,1.91-3.61,2.38-3.74.29-.08.78-.21,8.76,10.11.33.27,2.69,2.33,3.67,5.08,1.07,3-11.02,9.27-13.45,10.5-.07.06-12.73,9.26-17.92,11.87-2.06,1.04-4.5,1.36-6.72,1.36h0Z"
                    fill="#36344e" stroke-width="0"></path>
                <path
                    d="m405.03,281.31h-44.43l-4.03,41.33,17.64,152.71h22.68l-9.07-88.2,36.79,79.63,20.16-14.11-28.73-74.34s10.26-64.76,2.2-80.89c-8.06-16.13-13.21-16.13-13.21-16.13h0Z"
                    fill="#36344e" stroke-width="0"></path>
                <polygon points="427.13 283.83 356.57 283.83 377.74 192.6 408.48 192.6 427.13 283.83" fill="#6c63ff"
                    stroke-width="0"></polygon>
                <path id="uuid-7f4403b9-21a6-4777-8daf-6db0c322735b-89-44-52-44-91-153-48-48-54"
                    d="m339.05,190.81c-1.13-5.55.94-10.62,4.6-11.32,3.67-.7,7.55,3.23,8.68,8.78.48,2.21.41,4.5-.22,6.69l4.46,23.57-11.53,1.82-3.17-23.43c-1.43-1.79-2.4-3.88-2.82-6.11h0Z"
                    fill="#f2a7aa" stroke-width="0"></path>
                <path
                    d="m407.73,192.6h-27.11l-18.28,36.55-6.89-27.39-15.15,1.61s3.58,53.52,19.25,51.71,52.2-50.03,48.18-62.48h0Z"
                    fill="#6c63ff" stroke-width="0"></path>
                <path id="uuid-4f4c8d73-174a-4aab-b932-a11e56a3b943-90-45-53-45-92-154-49-49-55"
                    d="m438.01,331.62c1.13,5.55-.94,10.62-4.6,11.32-3.67.7-7.55-3.23-8.68-8.78-.48-2.21-.41-4.5.22-6.69l-4.46-23.57,11.53-1.82,3.17,23.43c1.43,1.79,2.4,3.88,2.82,6.11h0Z"
                    fill="#f2a7aa" stroke-width="0"></path>
                <path d="m392.28,192.6s15.41-.65,16.21,0c4.2,3.44,28.87,127.34,28.87,127.34h-15.62l-29.45-127.34h0Z"
                    fill="#6c63ff" stroke-width="0"></path>

            </svg>
            <div class="content-info">
                <h1 class="content-title">
                    Contact
                </h1>
                <p class="content-description">
                    Lorem ipsum dolor sit amet, consectetur adipiscing elit. Nullam eu nulla in tellus sagittis
                    malesuada sit amet ac urna. Fusce eget eleifend nulla, a congue sem.
                </p>
            </div>
        </div> --}}
    </div>
    </di>

    @forelse ($notifications as $date => $notificationGroup)
        @if ($loop->first)
            <div class="custom-date">
                <div class="line"></div>
                <h2>{{ $notificationGroup->date }}</h2>
                <div class="line"></div>
            </div>
        @endif
        <div class="notif-card">
            <div class="card-body d-flex align-items-center">
                <x-avatar width="35" height="35" />
                <div class="info ml-5">
                    <h5 class="name">{{ $notificationGroup?->user?->name ?? auth()->user()->name }} &nbsp;&nbsp;
                        <small>{{ $notificationGroup->created_at->diffForHumans() }}</small>
                    </h5>
                    <p class="message">{{ $notificationGroup->message }} </p>
                </div>
            </div>
        </div>
    @empty
        <div class="notif-card-empty">
            <div class="card-body d-flex align-items-center">
                <h5 class="mx-auto"><i class="fa fa-comments"></i> No Comment</h5>
            </div>
        </div>
    @endforelse


    <style>
        .commentContent {
            background: white;
        }

        .notif-card {
            border: 1px solid #c0bfbf;
            /* border-radius: 8px; */
            overflow: hidden;
            width: 100%;
            background: #f7f6f6bf;
        }

        .notif-card-empty {
            border-bottom: 1px solid #9c9c9c;
            overflow: hidden;
            width: 100%;
            background: #e0e0e0bf;
            height: 100%;
        }

        .notif-card .card-body {
            padding: 2px;
        }

        .notif-card .avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
        }

        .notif-card .info {
            flex-grow: 1;
        }

        .notif-card .name {
            margin: 0;
            font-size: 16px;
            margin-top: 2px;
            margin-left: 2px;
        }

        .notif-card .message {
            margin: 5px 0 0;
            font-size: 12px;
            color: #666;
            margin-left: 2px;

        }

        .highlight {
            color: rgb(37, 134, 173);
            /* Change to the color you desire */
        }
    </style>