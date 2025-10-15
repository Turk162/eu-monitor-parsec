$(document).ready(function() {
    $("#changePasswordForm").on("submit", function(e) {
        e.preventDefault();

        const oldPassword = $("#old_password").val();
        const newPassword = $("#new_password").val();
        const confirmPassword = $("#confirm_password").val();

        if (newPassword !== confirmPassword) {
            $.notify({
                icon: 'nc-icon nc-simple-remove',
                message: "Le nuove password non coincidono."
            }, {
                type: 'danger',
                timer: 4000
            });
            return;
        }

        $.ajax({
            url: '../api/update_password.php',
            type: 'POST',
            data: {
                old_password: oldPassword,
                new_password: newPassword
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    $.notify({
                        icon: 'nc-icon nc-check-2',
                        message: response.message
                    }, {
                        type: 'success',
                        timer: 4000
                    });
                    $("#changePasswordForm")[0].reset();
                } else {
                    $.notify({
                        icon: 'nc-icon nc-simple-remove',
                        message: response.message
                    }, {
                        type: 'danger',
                        timer: 4000
                    });
                }
            },
            error: function() {
                $.notify({
                    icon: 'nc-icon nc-simple-remove',
                    message: "Si è verificato un errore. Riprova."
                }, {
                    type: 'danger',
                    timer: 4000
                });
            }
        });
    });
});
