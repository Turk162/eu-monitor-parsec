$(document).ready(function() {
    // Display selected file names
    $('#reportFilesInput').on('change', function() {
        const files = this.files;
        const selectedFilesDiv = $('#selectedFiles');
        selectedFilesDiv.empty(); // Clear previous selections

        if (files.length > 0) {
            let fileNamesHtml = '';
            for (let i = 0; i < files.length; i++) {
                fileNamesHtml += `<span class="badge badge-secondary mr-1">${files[i].name}</span>`;
            }
            selectedFilesDiv.html(fileNamesHtml);
        }
    });
});
