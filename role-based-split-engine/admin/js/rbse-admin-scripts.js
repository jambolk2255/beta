jQuery(document).ready(function($) {
    'use strict';

    // --- Logic for Process New Split Page ---
    // Check if we are on the "Process New Split" page by looking for a specific element
    var form = $('#rbse-new-split-form');
    if (form.length) {
        var roleSplitsContainer = $('#rbse_role_splits_container');
        var addRoleButton = $('#rbse_add_role_split_button');
        var totalPercentageDisplay = $('#rbse_total_percentage_display');
        var percentageWarning = $('#rbse_percentage_warning');
        var submitButton = $('#rbse_submit_split_button');
        var ajaxResponseDiv = $('#rbse-ajax-response');
        var roleRowIndex = 0;

        function createRoleSplitRow(index) {
            var html = '<div class="rbse-role-split-row" style="margin-bottom: 10px; padding: 10px; border: 1px solid #ccd0d4; background-color: #f9f9f9;">';
            html += '<table class="form-table" style="margin-top:0;"><tbody><tr valign="top">';
            html += '<th scope="row" style="width: auto; padding-left:0; padding-right:10px;"><label for="rbse_roles_' + index + '_name">' + rbse_process_split_params.i18n.role + ':</label></th>';
            html += '<td style="padding-left:0; padding-right:10px;"><select id="rbse_roles_' + index + '_name" name="rbse_roles[' + index + '][name]" class="rbse-role-select" required>';
            html += '<option value="">-- ' + rbse_process_split_params.i18n.role + ' --</option>';
            $.each(rbse_process_split_params.roles, function(slug, name) {
                html += '<option value="' + slug + '">' + name + '</option>';
            });
            html += '</select></td>';
            html += '<th scope="row" style="width: auto; padding-left:10px; padding-right:10px;"><label for="rbse_roles_' + index + '_percentage">' + rbse_process_split_params.i18n.percentage + ':</label></th>';
            html += '<td style="padding-left:0; padding-right:10px;"><input type="number" id="rbse_roles_' + index + '_percentage" name="rbse_roles[' + index + '][percentage]" class="rbse-percentage-input small-text" step="0.01" min="0" max="100" required placeholder="0.00" /> %</td>';
            html += '<td style="padding-left:10px; padding-right:0px;"><button type="button" class="button button-link-delete rbse-remove-role-split">';
            html += '<span class="dashicons dashicons-trash"></span> ' + rbse_process_split_params.i18n.remove_role;
            html += '</button></td>';
            html += '</tr></tbody></table></div>';
            return html;
        }

        function updateTotalPercentage() {
            var total = 0;
            var isValid = true;
            $('.rbse-percentage-input').each(function() {
                var val = parseFloat($(this).val());
                if (!isNaN(val)) {
                    total += val;
                }
                if (this.checkValidity && !this.checkValidity()) {
                    isValid = false;
                }
            });
            totalPercentageDisplay.text(total.toFixed(2));

            if (Math.abs(total - 100.00) < 0.001 && isValid) {
                percentageWarning.hide();
                submitButton.prop('disabled', false);
            } else {
                var errorMsg = rbse_process_split_params.i18n.total_must_be_100;
                if (!isValid && rbse_process_split_params.i18n.invalid_percentage_entry) {
                    errorMsg += ' ' + rbse_process_split_params.i18n.invalid_percentage_entry;
                }
                percentageWarning.text(errorMsg).show();
                submitButton.prop('disabled', true);
            }
        }

        addRoleButton.on('click', function() {
            var newRowHtml = createRoleSplitRow(roleRowIndex);
            roleSplitsContainer.append(newRowHtml);
            $('#rbse_roles_' + roleRowIndex + '_name').focus();
            roleRowIndex++;
            updateTotalPercentage();
        });

        roleSplitsContainer.on('click', '.rbse-remove-role-split', function() {
            $(this).closest('.rbse-role-split-row').remove();
            updateTotalPercentage();
        });

        roleSplitsContainer.on('input change', '.rbse-percentage-input', function() {
            updateTotalPercentage();
        });
        
        roleSplitsContainer.on('change', '.rbse-role-select', function() {
            updateTotalPercentage(); 
        });

        if (roleSplitsContainer.children('.rbse-role-split-row').length === 0) {
            addRoleButton.trigger('click');
        } else {
            updateTotalPercentage(); 
        }
        
        form.on('submit', function(event) {
            event.preventDefault();
            updateTotalPercentage(); 

            if (submitButton.prop('disabled')) {
                ajaxResponseDiv.html('<div class="notice notice-error is-dismissible"><p>' + rbse_process_split_params.i18n.total_must_be_100 + '</p></div>');
                $('html, body').animate({ scrollTop: form.offset().top - 50 }, 500);
                return;
            }

            ajaxResponseDiv.html('<p><span class="spinner is-active" style="float:left; margin-right:5px;"></span> ' + rbse_process_split_params.i18n.processing + '</p>');
            submitButton.prop('disabled', true);

            var formData = $(this).serialize();
            formData += '&action=rbse_process_split'; 

            $.ajax({
                url: ajaxurl, 
                type: 'POST',
                data: formData,
                dataType: 'json', 
                success: function(response) {
                    if (response.success) {
                        ajaxResponseDiv.html('<div class="notice notice-success is-dismissible"><p>' + response.data.message + '</p></div>');
                        form[0].reset(); 
                        roleSplitsContainer.empty(); 
                        roleRowIndex = 0;
                        addRoleButton.trigger('click'); 
                        updateTotalPercentage(); 
                    } else {
                        ajaxResponseDiv.html('<div class="notice notice-error is-dismissible"><p>' + (response.data.message || rbse_process_split_params.i18n.error_occurred) + '</p></div>');
                    }
                },
                error: function(xhr, status, error) {
                    ajaxResponseDiv.html('<div class="notice notice-error is-dismissible"><p>' + rbse_process_split_params.i18n.error_occurred + ' (Status: ' + status + ')</p></div>');
                    console.error("AJAX Error:", xhr, status, error);
                },
                complete: function() {
                    updateTotalPercentage(); 
                    if (!ajaxResponseDiv.find('.notice').length) { 
                        ajaxResponseDiv.empty();
                    }
                }
            });
        });
    } // End of "Process New Split" page specific logic

    // --- Logic for Analytics Page ---
    // Check if we are on the Analytics page by looking for the chart canvas
    var roleChartCanvas = $('#rbseRoleAnalyticsChart');
    if (roleChartCanvas.length && typeof Chart !== 'undefined' && typeof rbse_analytics_params !== 'undefined') {
        var ctx = roleChartCanvas[0].getContext('2d');
        new Chart(ctx, {
            type: 'bar', // Or 'pie'
            data: {
                labels: rbse_analytics_params.role_chart_labels,
                datasets: [{
                    label: rbse_analytics_params.i18n.total_amount_by_role || 'Total Amount by Role',
                    data: rbse_analytics_params.role_chart_data,
                    backgroundColor: [ // Add more colors if more roles are expected
                        'rgba(255, 99, 132, 0.2)',
                        'rgba(54, 162, 235, 0.2)',
                        'rgba(255, 206, 86, 0.2)',
                        'rgba(75, 192, 192, 0.2)',
                        'rgba(153, 102, 255, 0.2)',
                        'rgba(255, 159, 64, 0.2)'
                    ],
                    borderColor: [
                        'rgba(255, 99, 132, 1)',
                        'rgba(54, 162, 235, 1)',
                        'rgba(255, 206, 86, 1)',
                        'rgba(75, 192, 192, 1)',
                        'rgba(153, 102, 255, 1)',
                        'rgba(255, 159, 64, 1)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                scales: {
                    y: {
                        beginAtZero: true
                    }
                },
                responsive: true,
                maintainAspectRatio: true // Set to false if you have specific height/width needs not met by canvas attributes
            }
        });
    } // End of Analytics page specific logic

});
