<?php
function show_year_options($startYear = null, $endYear = 1900, $selected = null)
{
    if ($startYear === null) {
        $startYear = date('Y');
    }

    $html = '';
    for ($year = $startYear; $year >= $endYear; $year--) {
        $isSelected = ($year == $selected) ? ' selected' : '';
        $html .= "<option value=\"$year\"$isSelected>$year</option>\n";
    }

    return $html;
}
$kids = tr_patient_get_kids($patient_id, $user_id);
?>


<div id="box_add_linked" style="display: none" ; class="fixed inset-0 bg-white opacity-90 flex items-center justify-center p-4 z-50">
    <div class="bg-white rounded-xl border border-gray-200 shadow-card max-w-md w-full max-h-[90vh] overflow-y-auto">
        <div class="sticky top-0 bg-white border-b border-gray-200 px-6 py-4 flex items-center justify-between rounded-t-xl">
            <h3 class="text-xl font-bold text-[#3C3D59]">Add Family Member</h3>
            <button onclick="close_add_linked();return false;" class="cursor-pointer text-gray-400 hover:text-gray-600 transition-colors">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-x w-6 h-6" aria-hidden="true">
                    <path d="M18 6 6 18"></path>
                    <path d="m6 6 12 12"></path>
                </svg>
            </button>
        </div>
        <form class="p-6 space-y-4">
            <p class="text-sm text-gray-600 mb-4">Enter the patient's information to link their account and access their lab results.</p>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Last Name</label>
                <input id="pa_last_name" type="text" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#D83D00] focus:border-transparent" placeholder="Enter last name" required="" value="">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">First Name</label>
                <input id="pa_first_name" type="text" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#D83D00] focus:border-transparent" placeholder="Enter first name" required="" value="">
            </div>

            <div><label class="block text-sm font-medium text-gray-700 mb-1.5">Birthday</label>
                <div class="grid grid-cols-3 gap-3">
                    <select id="pa_month" class="px-4 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#D83D00] focus:border-transparent" required="">
                        <option value="">Month</option>
                        <option value="01">January</option>
                        <option value="02">February</option>
                        <option value="03">March</option>
                        <option value="04">April</option>
                        <option value="05">May</option>
                        <option value="06">June</option>
                        <option value="07">July</option>
                        <option value="08">August</option>
                        <option value="09">September</option>
                        <option value="10">October</option>
                        <option value="11">November</option>
                        <option value="12">December</option>
                    </select>
                    <select id="pa_day" class="px-4 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#D83D00] focus:border-transparent" required="">
                        <option value="">Day</option>
                        <option value="01">1</option>
                        <option value="02">2</option>
                        <option value="03">3</option>
                        <option value="04">4</option>
                        <option value="05">5</option>
                        <option value="06">6</option>
                        <option value="07">7</option>
                        <option value="08">8</option>
                        <option value="09">9</option>
                        <option value="10">10</option>
                        <option value="11">11</option>
                        <option value="12">12</option>
                        <option value="13">13</option>
                        <option value="14">14</option>
                        <option value="15">15</option>
                        <option value="16">16</option>
                        <option value="17">17</option>
                        <option value="18">18</option>
                        <option value="19">19</option>
                        <option value="20">20</option>
                        <option value="21">21</option>
                        <option value="22">22</option>
                        <option value="23">23</option>
                        <option value="24">24</option>
                        <option value="25">25</option>
                        <option value="26">26</option>
                        <option value="27">27</option>
                        <option value="28">28</option>
                        <option value="29">29</option>
                        <option value="30">30</option>
                        <option value="31">31</option>
                    </select>

                    <select id="pa_year" class="px-4 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#D83D00] focus:border-transparent" required="">
                        <option value="">Year</option>
                        <?php echo show_year_options(null, 1900, $birth_year ?? null); ?>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Patient Code or Accession Code</label>
                <input id="patient_code" type="text" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#D83D00] focus:border-transparent" placeholder="Enter patient or accession code" required="" value="">
            </div>

            <div class="flex gap-3 pt-4">
                <button onclick="close_add_linked();return false;" data-slot="button" class="inline-flex cursor-pointer items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg:not([class*='size-'])]:size-4 [&amp;_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive border bg-background text-foreground hover:bg-accent hover:text-accent-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 h-9 px-4 py-2 has-[&gt;svg]:px-3 flex-1" type="button">Cancel</button>
                <button onclick="act_add_linked();return false;" data-slot="button" class="inline-flex cursor-pointer items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg:not([class*='size-'])]:size-4 [&amp;_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive h-9 px-4 py-2 has-[&gt;svg]:px-3 flex-1 bg-[#D83D00] hover:bg-[#c03600] text-white" type="submit">Link Patient</button>
            </div>
        </form>
    </div>
</div>

<?php if (!isset($include_page)) { ?>
    <main class="p-6 bg-[#f8f9fa] overflow-auto flex-1">
        <div class="space-y-6">
        <?php } ?>
        <div data-slot="card" class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card overflow-hidden">
            <div data-slot="card-header" class="@container/card-header grid auto-rows-min grid-rows-[auto_auto] items-start gap-1.5 px-6 pt-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6 border-b border-gray-200 bg-gray-50">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-users w-5 h-5 text-[#D83D00]" aria-hidden="true">
                            <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path>
                            <path d="M16 3.128a4 4 0 0 1 0 7.744"></path>
                            <path d="M22 21v-2a4 4 0 0 0-3-3.87"></path>
                            <circle cx="9" cy="7" r="4"></circle>
                        </svg>
                        <h3 class="text-lg font-semibold text-[#3C3D59]">Family Members</h3>
                    </div>

                    <button onclick="show_add_linked();return false;" data-slot="button" class="inline-flex cursor-pointer items-center justify-center gap-2 whitespace-nowrap rounded-md font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg:not([class*='size-'])]:size-4 shrink-0 [&amp;_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive h-9 px-4 py-2 has-[&gt;svg]:px-3 bg-[#D83D00] hover:bg-[#c03600] text-white text-sm">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-user-plus w-4 h-4 mr-2" aria-hidden="true">
                            <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path>
                            <circle cx="9" cy="7" r="4"></circle>
                            <line x1="19" x2="19" y1="8" y2="14"></line>
                            <line x1="22" x2="16" y1="11" y2="11"></line>
                        </svg>Add Family Members
                    </button>

                </div>
            </div>
            <div data-slot="card-content" class="[&amp;:last-child]:pb-6 p-6">
                <?php if (isset($include_page)) { ?>
                    <p class="text-sm text-gray-600 mb-4">Manage access to results for family members and dependents</p>
                <?php } ?>
                <div class="space-y-3">
                    <?php
                    foreach ($kids as $idKid => $k) {
                        $patient_kid = $wpdb->get_row("select * from ci_patients where pa_ID = {$idKid} ");
                        $pa_gender = $patient_kid->pa_gender;
                        $pa_sex_text = '';
                        $pa_mrn_text = '';
                        $last = trim((string) ($patient_kid->pa_last ?? ''));
                        $avatar_text = strtoupper(mb_substr($last, 0, 2, 'UTF-8'));
                        if ($pa_gender == 'F')
                            $pa_sex_text = 'Daughter';
                        else
                            $pa_sex_text = 'Son';

                        if ($patient_kid->pa_mrn != '') {
                            $pa_mrn_text = ' • ' . $patient_kid->pa_mrn;
                        }

                    ?>
                        <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg border border-gray-200">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-full bg-[#D83D00] flex items-center justify-center text-white font-semibold">
                                    <?php echo $avatar_text ?>
                                </div>
                                <div>
                                    <p class="font-medium text-gray-900"><?php echo $k['last_name'] ?> <?php echo $k['first_name'] ?></p>
                                    <p class="text-sm text-gray-600"><?php echo $pa_sex_text; ?><?php echo $pa_mrn_text; ?></p>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <button id_pa="<?php echo $idKid; ?>" data-slot="button" class="btn_view_result cursor-pointer inline-flex items-center justify-center whitespace-nowrap font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg:not([class*='size-'])]:size-4 shrink-0 [&amp;_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive border bg-background text-foreground hover:bg-accent hover:text-accent-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 h-8 rounded-md gap-1.5 px-3 has-[&gt;svg]:px-2.5 text-sm">View Results</button>
                                <button id_kid="<?php echo $idKid; ?>" data-slot="button" class="btn_remove_kid cursor-pointer inline-flex items-center justify-center whitespace-nowrap font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg:not([class*='size-'])]:size-4 shrink-0 [&amp;_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive border bg-background hover:bg-accent dark:bg-input/30 dark:border-input dark:hover:bg-input/50 h-8 rounded-md gap-1.5 px-3 has-[&gt;svg]:px-2.5 text-sm text-red-600 hover:text-red-700">Remove</button>
                            </div>
                        </div>
                    <?php } ?>

                    <?php if (!isset($include_page)) { ?>
                        <div class="text-center py-8 text-gray-500 text-sm">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-users w-12 h-12 text-gray-300 mx-auto mb-2" aria-hidden="true">
                                <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path>
                                <path d="M16 3.128a4 4 0 0 1 0 7.744"></path>
                                <path d="M22 21v-2a4 4 0 0 0-3-3.87"></path>
                                <circle cx="9" cy="7" r="4"></circle>
                            </svg>
                            <p>Link family members to view their results</p>
                        </div>
                    <?php } ?>

                </div>
            </div>
        </div>
        <?php if (!isset($include_page)) { ?>
        </div>
    </main>
<?php } ?>

<script>
    function show_add_linked() {
        $("#box_add_linked").show();
    }

    function close_add_linked() {
        $("#box_add_linked").hide();
    }

    function delete_linked(kidID) {
        Swal.fire({
            title: 'Linked Patients',
            text: 'Loading...Please wait...',
            allowOutsideClick: false,
            allowEscapeKey: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        $.ajax({
            url: '<?php echo home_url(); ?>/patient_web_api/removekid',
            type: 'POST',
            dataType: 'json',
            data: {
                kidid: kidID,
                user_id: '<?php echo $user_id ?>',
            },
            success: function(response) {
                Swal.close();
                if (response.status == "ok") {
                    Swal.fire({
                        title: 'Success',
                        html: `
                                ${response.message}<br><br>
                                <b>The page will refresh in <span id="countdown"></span> seconds...</b>
                            `,
                        icon: 'success',
                        showConfirmButton: true,
                        confirmButtonText: 'OK',
                        timer: 6000,
                        timerProgressBar: true,
                        didOpen: () => {
                            const countdown = Swal.getHtmlContainer().querySelector('#countdown');
                            const timer = setInterval(() => {
                                countdown.textContent = Math.ceil(Swal.getTimerLeft() / 1000);
                            }, 100);
                        }
                    }).then((result) => {
                        if (result.isConfirmed || result.dismiss === Swal.DismissReason.timer) {
                            window.location = "<?php echo home_url('patient/linked'); ?>";
                        }
                    });
                } else {
                    Swal.fire({
                        title: 'Error',
                        html: response.message,
                        icon: 'error'
                    });
                }
            },
            error: function(xhr, status, error) {
                Swal.fire({
                    title: 'Error',
                    html: error,
                    icon: 'error'
                });
            }
        });
    }

    function act_add_linked() {
        var birthday = $('#pa_year').val() + '-' + $('#pa_month').val() + '-' + $('#pa_day').val();

        Swal.fire({
            title: 'Profile',
            text: 'Saving...Please wait...',
            allowOutsideClick: false,
            allowEscapeKey: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        $.ajax({
            url: '<?php echo home_url(); ?>/patient_web_api/add_patient_linked',
            type: 'POST',
            dataType: 'json',
            data: {
                patient_id: '<?php echo $patient_id ?>',
                user_id: '<?php echo $user_id ?>',
                birthday,
                first_name: $("#pa_first_name").val(),
                last_name: $("#pa_last_name").val(),
                patient_code: $("#patient_code").val()
            },
            success: function(response) {
                Swal.close();
                if (response.status == "ok") {
                    Swal.fire({
                        title: 'Success',
                        html: `
                                ${response.message}<br><br>
                                <b>The page will refresh in <span id="countdown"></span> seconds...</b>
                            `,
                        icon: 'success',
                        showConfirmButton: true,
                        confirmButtonText: 'OK',
                        timer: 6000,
                        timerProgressBar: true,
                        didOpen: () => {
                            const countdown = Swal.getHtmlContainer().querySelector('#countdown');
                            const timer = setInterval(() => {
                                countdown.textContent = Math.ceil(Swal.getTimerLeft() / 1000);
                            }, 100);
                        }
                    }).then((result) => {
                        if (result.isConfirmed || result.dismiss === Swal.DismissReason.timer) {
                            window.location = "<?php echo home_url('patient/linked'); ?>";
                        }
                    });
                } else {
                    Swal.fire({
                        title: 'Error',
                        html: response.message,
                        icon: 'error'
                    });
                }
            },
            error: function(xhr, status, error) {
                console.error('Error:', error);
                $("#box_add_linked").hide();
            }
        });
        return false;
    }

    $(document).ready(function() {
        $(".btn_remove_kid").click(function() {
            var kidID = $(this).attr("id_kid");
            Swal.fire({
                title: 'Confirm deletion?',
                text: 'The data, once deleted, cannot be recovered!',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Delete',
                cancelButtonText: 'Cancel',
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6'
            }).then((result) => {
                if (result.isConfirmed) {
                    delete_linked(kidID);
                }
            });
            return false;
        });

        $(".btn_view_result").click(function() {
            var id_pa = $(this).attr("id_pa");
            window.location = "<?php echo home_url('patient/results?idPa=') ?>" + id_pa;
        });
    });
</script>