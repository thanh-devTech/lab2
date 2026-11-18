<main class="p-6 bg-[#f8f9fa] overflow-auto flex-1">
    <div class="space-y-6">
        <form method="POST" data-slot="card" class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card overflow-hidden">
            <div data-slot="card-header" class="@container/card-header grid auto-rows-min grid-rows-[auto_auto] items-start gap-1.5 px-6 pt-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6 border-b border-gray-200 bg-gray-50">
                <div class="flex items-center gap-2"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-user w-5 h-5 text-[#D83D00]" aria-hidden="true">
                        <path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"></path>
                        <circle cx="12" cy="7" r="4"></circle>
                    </svg>
                    <h3 class="text-lg font-semibold text-[#3C3D59]">Identity</h3>
                </div>
            </div>
            <div data-slot="card-content" class="[&amp;:last-child]:pb-6 p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">First Name</label>
                        <input id="pa_first" type="text" readonly class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#D83D00] focus:border-transparent bg-gray-50 cursor-not-allowed" value="<?php echo $patient->pa_first ?? ''; ?>">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Last Name</label>
                        <input id="pa_last" type="text" readonly class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#D83D00] focus:border-transparent bg-gray-50 cursor-not-allowed" value="<?php echo $patient->pa_last ?? ''; ?>">
                    </div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-1.5">Date of Birth</label>
                        <div class="relative">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-calendar absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400" aria-hidden="true">
                                <path d="M8 2v4"></path>
                                <path d="M16 2v4"></path>
                                <rect width="18" height="18" x="3" y="4" rx="2"></rect>
                                <path d="M3 10h18"></path>
                            </svg>
                            <input id="pa_dob" type="text" readonly class="w-full pl-10 pr-4 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#D83D00] focus:border-transparent bg-gray-50 cursor-not-allowed" value="<?php echo $patient->pa_dob ?? ''; ?>">
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">MRN</label>
                        <input id="pa_mrn" type="text" readonly class="w-full px-4 py-2.5 border border-gray-300 rounded-lg bg-gray-50 cursor-not-allowed" value="<?php echo $patient->pa_mrn ?? ''; ?>">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Email</label>
                        <div class="relative"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-mail absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400" aria-hidden="true">
                                <path d="m22 7-8.991 5.727a2 2 0 0 1-2.009 0L2 7"></path>
                                <rect x="2" y="4" width="20" height="16" rx="2"></rect>
                            </svg>
                            <input id="pa_email" type="email" readonly class="w-full pl-10 pr-4 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#D83D00] focus:border-transparent bg-gray-50 cursor-not-allowed" value="<?php echo $patient->pa_email ?? ''; ?>">
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Phone Number</label>
                        <div class="relative"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-phone absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400" aria-hidden="true">
                                <path d="M13.832 16.568a1 1 0 0 0 1.213-.303l.355-.465A2 2 0 0 1 17 15h3a2 2 0 0 1 2 2v3a2 2 0 0 1-2 2A18 18 0 0 1 2 4a2 2 0 0 1 2-2h3a2 2 0 0 1 2 2v3a2 2 0 0 1-.8 1.6l-.468.351a1 1 0 0 0-.292 1.233 14 14 0 0 0 6.392 6.384"></path>
                            </svg>
                            <input id="pa_phone" type="tel" readonly class="w-full pl-10 pr-4 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#D83D00] focus:border-transparent bg-gray-50 cursor-not-allowed" value="<?php echo $patient->pa_phone ?? ''; ?>">
                        </div>
                    </div>
                </div>
            </div>
        </form>

        <?php
        $include_page = 1;
        include "patient_linked.php";
        ?>

    </div>
</main>



<script>
    $(document).ready(function() {
        $("#btn_save").click(function() {
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
                url: '<?php echo home_url(); ?>/patient_web_api/update_patient',
                type: 'POST',
                dataType: 'json',
                data: {
                    patient_id: '<?php echo $patient_id ?>',
                    user_id: '<?php echo $user_id ?>',
                    pa_mrn: $("#pa_mrn").val(),
                    pa_email: $("#pa_email").val(),
                    pa_phone: $("#pa_phone").val(),
                    pa_dob: $("#pa_dob").val(),
                    pa_first: $("#pa_first").val(),
                    pa_last: $("#pa_last").val(),
                },
                success: function(response) {
                    Swal.close();
                    if (response.status == "ok") {
                        Swal.fire({
                            title: 'success',
                            text: response.message,
                            icon: 'success'
                        });
                    } else {
                        Swal.fire({
                            title: 'Error',
                            text: response.message,
                            icon: 'error'
                        });
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error:', error);
                }
            });


            return false;
        });
    });
</script>