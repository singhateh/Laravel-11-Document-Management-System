   <!-- Modal for file preview -->
   <div class="overlay" style="display: none">

       <i class="fa fa-times close-icon" onclick="closeOverlay()"></i>
   </div>
   <div class="modal" id="previewModal" tabindex="-1" role="dialog" data-backdrop="static"
       aria-labelledby="previewModalLabel" aria-hidden="true">
       <i class="fa fa-times close-icon" onclick="closeOverlay()"></i>
       <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable" role="document">
           <div class="modal-content">
               <div class="modal-body">
                   <div class="embed-responsive embed-responsive-16by9">
                       <div id="previewContent" class="embed-responsive-item"></div>
                   </div>
               </div>
           </div>
       </div>
   </div>
