$(document).ready(function() {
    // Function to handle file input changes
    function handleFileInputChange() {
        var file = this.files[0];
        if (file) {
            var fileList = $(this).closest('.custom-file').next('.file-list');
            fileList.html('<p><i class="nc-icon nc-paper"></i> ' + file.name + ' (' + (file.size / 1024).toFixed(2) + ' KB)</p>');
        }
    }

    // Attach event listener to existing file inputs
    $(document).on('change', '.custom-file-input', handleFileInputChange);

    // Javascript for adding/removing personnel entries
    $('.add-personnel').click(function() {
        var wpId = $(this).data('wp-id');
        var newEntry = `
            <div class="personnel-entry mb-3 border p-3 rounded">
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label>Personnel Name</label>
                        <input type="text" name="personnel_name[${wpId}][]" class="form-control" placeholder="Ex. Full Name">
                    </div>
                    <div class="form-group col-md-4">
                        <label>Working Days</label>
                        <input type="number" name="working_days[${wpId}][]" class="form-control" placeholder="Ex. 10">
                    </div>
                    <div class="form-group col-md-2 d-flex align-items-end">
                        <button type="button" class="btn btn-danger btn-round btn-sm remove-personnel">Remove</button>
                    </div>
                </div>
                <div class="form-group col-md-12 mt-3">
                    <label>Personnel Attachments:</label>
                    <div class="custom-file mb-2">
                        <input type="file" name="letter_of_assignment[${wpId}][]" class="custom-file-input" lang="en">
                        <label class="custom-file-label">Letter of Assignment</label>
                    </div><div class="file-list"></div>
                    <div class="custom-file mb-2">
                        <input type="file" name="timesheet[${wpId}][]" class="custom-file-input" lang="en">
                        <label class="custom-file-label">Timesheet</label>
                    </div><div class="file-list"></div>
                    <div class="custom-file">
                        <input type="file" name="invoices[${wpId}][]" class="custom-file-input" lang="en">
                        <label class="custom-file-label">Invoices</label>
                    </div><div class="file-list"></div>
                </div>
            </div>
        `;
        $(newEntry).insertBefore($(this));
    });

    $(document).on('click', '.remove-personnel', function() {
        $(this).closest('.personnel-entry').remove();
    });

    // Javascript for adding/removing mobility entries
    $('.add-mobility').click(function() {
        var wpId = $(this).data('wp-id');
        var newMobilityEntry = `
            <div class="mobility-entry mb-3 border p-3 rounded">
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label>Mobility Name</label>
                        <select name="mobility_name[${wpId}][]" class="form-control">
                            <option>Mobility Placeholder 1</option>
                            <option>Mobility Placeholder 2</option>
                        </select>
                    </div>
                    <div class="form-group col-md-4">
                        <label>Mobility Attachments:</label>
                        <div class="custom-file mb-2">
                            <input type="file" name="boarding_cards[${wpId}][]" class="custom-file-input" lang="en">
                            <label class="custom-file-label">Boarding Cards</label>
                        </div><div class="file-list"></div>
                        <div class="custom-file">
                            <input type="file" name="mobility_invoices[${wpId}][]" class="custom-file-input" lang="en">
                            <label class="custom-file-label">Invoices</label>
                        </div><div class="file-list"></div>
                    </div>
                    <div class="form-group col-md-2 d-flex align-items-end">
                        <button type="button" class="btn btn-danger btn-round btn-sm remove-mobility">Remove</button>
                    </div>
                </div>
            </div>
        `;
        $(newMobilityEntry).insertBefore($(this));
    });

    $(document).on('click', '.remove-mobility', function() {
        $(this).closest('.mobility-entry').remove();
    });
});