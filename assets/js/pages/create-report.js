$(document).ready(function() {
    const fileInput = $('#fileInput');
    const uploadWrapper = $('.file-upload-wrapper');
    const listWrapper = $('#file-list-wrapper');
    const fileList = $('#file-list');
    const clearBtn = $('#clear-files-btn');

    fileInput.on('change', function() {
        const files = $(this)[0].files;

        if (files.length > 0) {
            uploadWrapper.hide();
            listWrapper.show();
            fileList.html(''); // Clear previous list

            let listHtml = '';
            for (let i = 0; i < files.length; i++) {
                const file = files[i];
                const fileSize = (file.size / 1024 / 1024).toFixed(2); // in MB
                listHtml += '<li class="list-group-item d-flex justify-content-between align-items-center">';
                listHtml += '<span><i class="nc-icon nc-paper"></i> ' + file.name + '</span>';
                listHtml += '<span class="badge badge-primary badge-pill">' + fileSize + ' MB</span>';
                listHtml += '</li>';
            }
            fileList.html(listHtml);
        }
    });

    clearBtn.on('click', function() {
        fileInput.val(''); // Clear the file input
        listWrapper.hide();
        uploadWrapper.show();
    });
});
