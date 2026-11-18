<?php
global $wpdb;
global $ci_pdf_filename;
global $_ci_override_pregnancy;
global $_ci_exclude_antibiotic_IDs;
global $_ci_override_renal;

$_ci_override_pregnancy = null;
$trajax                 = isset($_REQUEST['trajax']) ? $_REQUEST['trajax'] : '';
$user                   = wp_get_current_user();
$comment                = '';
$addmailing             = false;
$sent                   = false;
$results                = [];

if ($trajax == 'search_report') {
    $search = trim($_REQUEST['term']);
    $sql    = $wpdb->prepare("select assen, CONCAT(assen,' - ', patient_name)  as label
	 from ci_batches_pcr where assen = %s or patient_name like %s", $search, '%' . $search . '%');
    $rs     = $wpdb->get_results($sql, ARRAY_A);
    wp_send_json($rs);
    exit;
} else if ($trajax === 'apply_filter') {


    $pdf_has_report     = true;
    $_GET['viewpdf']    = true;
    $ci_pdf_filename    = '';
    $_GET['nocacheabx'] = true;
    $_ci_exclude_antibiotic_IDs = array();

    if (isset($_REQUEST['pregnancy'])) {
        if ($_REQUEST['pregnancy'] == 'yes') {
            $_ci_override_pregnancy = true;
        } else if ($_REQUEST['pregnancy'] == 'no') {
            $_ci_override_pregnancy = false;
        }
    }

    $antibiotic = sanitize_text_field($_REQUEST['antibiotic']);
    $severity   = sanitize_text_field($_REQUEST['severity']);
    $assen      = sanitize_text_field($_REQUEST['assen']);
    $_ci_override_renal = isset($_REQUEST['renal']) && $_REQUEST['renal'] !== '' ? $_REQUEST['renal'] : null;

    $rsclass = $wpdb->get_results("SELECT * FROM ci_abx_antibio_class");
    $abx_classes = array();
    foreach ($rsclass as $rc) {
        $abx_classes[strtolower($rc->class)] = $rc->ID;
    }

    $all_excludes = array(
        'Penicillins' => array(
            'mild' => array('Penicillins', 'Amoxicillin/Clavulanate', 'Ampicillin/Sulbactam', 'Piperacillin/Tazobactam'),
            'severe' => array(
                'Penicillins',
                'Amoxicillin/Clavulanate',
                'Ampicillin/Sulbactam',
                'Piperacillin/Tazobactam',
                '1G Cephalosporins',
                '2G Cephalosporins',
                '2G Cephamycins',
                '3G Oral Cephalosporins',
                '3G IV Cephalosporins',
                '3G Oxy-Cephalosporins',
                '4G Cefepime',
                '5G Ceftaroline',
                '5G Cefiderocol',
                'Cef-avibactam',
                'Cef-tazobactam',
                'Carbapenems',
                'Imi-cil-reb',
                'Mero-vaborbactam',
                'Monobactam'
            )
        ),
        'Cephalosporins' => array(
            'mild' => array(
                '1G Cephalosporins',
                '2G Cephalosporins',
                '2G Cephamycins',
                '3G Oral Cephalosporins',
                '3G IV Cephalosporins',
                '3G Oxy-Cephalosporins',
                '4G Cefepime',
                '5G Ceftaroline',
                '5G Cefiderocol',
                'Cef-avibactam',
                'Cef-tazobactam'
            ),
            'severe' => array(
                'Penicillins',
                'Amoxicillin/Clavulanate',
                'Ampicillin/Sulbactam',
                'Piperacillin/Tazobactam',
                '1G Cephalosporins',
                '2G Cephalosporins',
                '2G Cephamycins',
                '3G Oral Cephalosporins',
                '3G IV Cephalosporins',
                '3G Oxy-Cephalosporins',
                '4G Cefepime',
                '5G Ceftaroline',
                '5G Cefiderocol',
                'Cef-avibactam',
                'Cef-tazobactam',
                'Carbapenems',
                'Imi-cil-reb',
                'Mero-vaborbactam',
                'Monobactam'
            )
        ),
        'Carbapenems' => array(
            'mild' => array('Carbapenems', 'Imi-cil-reb', 'Mero-vaborbactam'),
            'severe' => array(
                'Penicillins',
                'Amoxicillin/Clavulanate',
                'Ampicillin/Sulbactam',
                'Piperacillin/Tazobactam',
                '1G Cephalosporins',
                '2G Cephalosporins',
                '2G Cephamycins',
                '3G Oral Cephalosporins',
                '3G IV Cephalosporins',
                '3G Oxy-Cephalosporins',
                '4G Cefepime',
                '5G Ceftaroline',
                '5G Cefiderocol',
                'Cef-avibactam',
                'Cef-tazobactam',
                'Carbapenems',
                'Imi-cil-reb',
                'Mero-vaborbactam',
                'Monobactam'
            )
        ),
        'Monobactams' => array(
            'mild' => array('Monobactam'),
            'severe' => array(
                'Penicillins',
                'Amoxicillin/Clavulanate',
                'Ampicillin/Sulbactam',
                'Piperacillin/Tazobactam',
                '1G Cephalosporins',
                '2G Cephalosporins',
                '2G Cephamycins',
                '3G Oral Cephalosporins',
                '3G IV Cephalosporins',
                '3G Oxy-Cephalosporins',
                '4G Cefepime',
                '5G Ceftaroline',
                '5G Cefiderocol',
                'Cef-avibactam',
                'Cef-tazobactam',
                'Carbapenems',
                'Imi-cil-reb',
                'Mero-vaborbactam',
                'Monobactam'
            )
        ),
        'Fluoroquinolones' => array(
            'mild' => array('Fluoroquinolones'),
            'severe' => array('Fluoroquinolones')
        ),
        'Sulfonamides' => array(
            'mild' => array('Sulfonamides'),
            'severe' => array('Sulfonamides')
        ),
        'Nitrofurantoin' => array(
            'mild' => array('Nitrofurantoin'),
            'severe' => array('Nitrofurantoin')
        ),
        'Fosfomycin' => array(
            'mild' => array('Fosfomycin'),
            'severe' => array('Fosfomycin')
        ),
        'Aminoglycosides' => array(
            'mild' => array('Aminoglycosides'),
            'severe' => array('Aminoglycosides')
        ),
        'Polymyxins' => array(
            'mild' => array('Polymyxins'),
            'severe' => array('Polymyxins')
        ),
        'Lincosamides' => array(
            'mild' => array('Lincosamides'),
            'severe' => array('Lincosamides')
        ),
        'Macrolides' => array(
            'mild' => array('Macrolides'),
            'severe' => array('Macrolides')
        ),
        'Streptogramins' => array(
            'mild' => array('Streptogramins'),
            'severe' => array('Streptogramins')
        ),
        'Lipopeptides' => array(
            'mild' => array('Lipopeptides'),
            'severe' => array('Lipopeptides')
        ),
        'Glycopeptides' => array(
            'mild' => array('Glycopeptides', 'VRE Glycopeptides'),
            'severe' => array('Glycopeptides', 'VRE Glycopeptides')
        ),
        'Tetracyclines' => array(
            'mild' => array('Tetracyclines'),
            'severe' => array('Tetracyclines')
        ),
        'Glycylcyclines' => array(
            'mild' => array('Glycylcyclines'),
            'severe' => array('Glycylcyclines')
        ),
        'Oxazolidinones' => array(
            'mild' => array('Oxazolidinones'),
            'severe' => array('Oxazolidinones')
        ),
        'Metronidazole' => array(
            'mild' => array('Metronidazole'),
            'severe' => array('Metronidazole')
        ),
        'Rifamycins' => array(
            'mild' => array('Rifamycins'),
            'severe' => array('Rifamycins')
        )
    );


    $exclude_antibiotics = isset($all_excludes[$antibiotic][$severity]) ? $all_excludes[$antibiotic][$severity] : array();


    foreach ($exclude_antibiotics as $anti) {

        $annames = array($anti);
        if (strpos($anti, '/') !== false) {
            $annames[] = str_replace('/', '-', $anti);
        }
        $annames_str = "'" . implode("','", $annames) . "'";
        $antiID = $wpdb->get_var("select ID from ci_abx_antibiotics where abx IN (" . $annames_str . ") or abb in (" . $annames_str . ") limit 1");
        if ($antiID > 0) {
            $_ci_exclude_antibiotic_IDs[] = $antiID;
        } else if (isset($abx_classes[strtolower($anti)])) {
            $class_id = $abx_classes[strtolower($anti)];
            $rsabx = $wpdb->get_results($wpdb->prepare("SELECT ID FROM ci_abx_antibiotics WHERE class_ID = %d", $class_id));
            foreach ($rsabx as $ra) {
                $_ci_exclude_antibiotic_IDs[] = $ra->ID;
            }
        }
    }

    $exclude_antibiotics = array_unique($exclude_antibiotics);
    $exclude_antibiotics = array_map('strtolower', $exclude_antibiotics);


    if (isset($_GET['debugex'])) {
        var_dump($exclude_antibiotics);
        echo '<br><br>';
        var_dump($_ci_exclude_antibiotic_IDs);
        exit;
    }


    $id = $wpdb->get_var($wpdb->prepare("SELECT id FROM ci_reports_pcr WHERE patient_id = %s", $assen));
    $outfile = trpdd_export_results_pdf($id, 'filter', false, 'pcr');

    header('Content-Type: application/pdf');
    header('Content-Disposition: ; filename="' . basename($ci_pdf_filename) . '"');
    @readfile($outfile);
    exit;
}
wp_enqueue_script('jquery-ui-autocomplete');
?>
<style>
    h1,
    h4 {
        margin: unset;
    }

    h1 {
        margin-bottom: 5px !important;
    }

    h4 {
        margin-bottom: 5px;
    }

    h5 {
        font-weight: var(--font-weight-medium);
        margin: 0px;
        --tw-font-weight: var(--font-weight-medium);
        font-size: 13px;
        margin-bottom: 10.5px;
    }

    h1,
    h2,
    h3,
    h4,
    h5,
    h6 {
        font-weight: unset !important;
        font-size: inherit;
    }

    h4 {
        font-size: 14.5px;
    }

    input[type=button],
    input[type=email],
    input[type=search],
    input[type=submit],
    input[type=tel],
    input[type=text],
    select,
    textarea,
    .selected {
        border-radius: calc(var(--radius) - 2px) !important;
        background-color: var(--color-yellow-50) !important;
        border: 1px solid #e2e2e2ff;
    }

    .custom-select .selected {
        background-color: white !important;
        border-color: transparent;
    }


    p {
        margin-bottom: 10px;
    }

    .custom-select .options {
        right: 0;
    }
</style>
<script type="text/javascript" src="<?php echo TRCPDD_URL . 'js/admin_dashboard.js?' . TRCPDD_VS ?>"></script>
<div class="wrap">
    <div class="flex-1 overflow-auto p-4 sm:p-6 lg:p-8" style="min-height: 100vh;">
        <div class="space-y-6">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 sm:gap-0">
                <div>
                    <h1 style="font-size: 23px; font-weight: 400; margin: 0px; padding: 9px 0px 4px; line-height: 1.3;">Clinical Filters</h1>
                    <p class="text-muted-foreground mt-1">Apply clinical filters to igniteABX reports for personalized recommendations.</p>
                </div>
            </div>
            <div>
                <div class="grid grid-cols-3 gap-6">
                    <div class="col-span-2 space-y-6">
                        <div data-slot="card"
                            class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card">
                            <div data-slot="card-header"
                                class="@container/card-header grid auto-rows-min grid-rows-[auto_auto] items-start gap-1.5 px-6 pt-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6">
                                <h4 data-slot="card-title" class="leading-none flex items-center gap-2">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-funnel h-5 w-5 text-primary" aria-hidden="true">
                                        <path d="M10 20a1 1 0 0 0 .553.895l2 1A1 1 0 0 0 14 21v-7a2 2 0 0 1 .517-1.341L21.74 4.67A1 1 0 0 0 21 3H3a1 1 0 0 0-.742 1.67l7.225 7.989A2 2 0 0 1 10 14z"></path>
                                    </svg>Apply Clinical Filters
                                </h4>
                            </div>
                            <div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6">
                                <form class="space-y-6">
                                    <div class="space-y-2">
                                        <label data-slot="label"
                                            class="flex items-center gap-2 text-sm leading-none font-medium select-none group-data-[disabled=true]:pointer-events-none group-data-[disabled=true]:opacity-50 peer-disabled:cursor-not-allowed peer-disabled:opacity-50"
                                            for="report-select">Select Report (Accession/Patient name):</label>
                                        <input data-slot="input"
                                            class="file:text-foreground placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground dark:bg-input/30 flex h-9 w-full min-w-0 rounded-md border px-3 py-1 text-sm transition-[color,box-shadow] outline-none file:inline-flex file:h-7 file:border-0 file:bg-transparent file:text-sm file:font-medium disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive bg-yellow-50 border-gray-300 focus:bg-yellow-50"
                                            id="report-select" placeholder="Enter accession number or patient name" value="" style="font-size: 0.875rem;">
                                    </div>
                                    <div class="space-y-4 p-4 bg-rose-50 border rounded-lg shadow-card">
                                        <div class="flex items-center gap-2 mb-3"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-baby h-5 w-5 text-rose-500" aria-hidden="true">
                                                <path d="M10 16c.5.3 1.2.5 2 .5s1.5-.2 2-.5"></path>
                                                <path d="M15 12h.01"></path>
                                                <path d="M19.38 6.813A9 9 0 0 1 20.8 10.2a2 2 0 0 1 0 3.6 9 9 0 0 1-17.6 0 2 2 0 0 1 0-3.6A9 9 0 0 1 12 3c2 0 3.5 1.1 3.5 2.5s-.9 2.5-2 2.5c-.8 0-1.5-.4-1.5-1"></path>
                                                <path d="M9 12h.01"></path>
                                            </svg>
                                            <label class="font-medium text-rose-800" for="pregnant-lactating">Pregnancy/Lactation Indicator</label>
                                        </div>
                                        <select id="pregnant-lactating" aria-hidden="true" tabindex="-1"
                                            style="position: absolute; border: 0px; width: 1px; height: 1px; padding: 0px; margin: -1px; overflow: hidden; clip: rect(0px, 0px, 0px, 0px); white-space: nowrap; overflow-wrap: normal;">
                                            <option value="">Select</option>
                                            <option value="no">Not Pregnant/Lactating</option>
                                            <option value="yes">Pregnant/Lactating</option>
                                        </select>
                                    </div>

                                    <div class="space-y-4 p-4 bg-orange-50 border rounded-lg shadow-card">
                                        <div class="flex items-center gap-2 mb-3"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-triangle-alert h-5 w-5 text-orange-600" aria-hidden="true">
                                                <path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"></path>
                                                <path d="M12 9v4"></path>
                                                <path d="M12 17h.01"></path>
                                            </svg>
                                            <label class="font-medium text-orange-900">Allergies</label>
                                        </div>
                                        <select id="antibiotic" data-multi="1" data-search="1" data-placeholder="Select antibiotic class">
                                            <option value="">Select antibiotic class</option>
                                            <option value="Penicillins">
                                                <span>Penicillins</span><span class='text-sm text-gray-500 ml-1'>(amoxicillin, ampicillin, penicillin V/G, amox/clav, pip/tazo)</span>
                                            </option>
                                            <option value="Cephalosporins">
                                                <span>Cephalosporins</span><span class='text-sm text-gray-500 ml-1'>(cephalexin, cefazolin, cefuroxime, cefdinir, ceftriaxone)</span>
                                            </option>
                                            <option value="Carbapenems">
                                                <span>Carbapenems</span><span class='text-sm text-gray-500 ml-1'>(meropenem, ertapenem, imipenem/cilastatin)</span>
                                            </option>
                                            <option value="Monobactams">
                                                <span>Monobactams</span><span class='text-sm text-gray-500 ml-1'>(aztreonam)</span>
                                            </option>
                                            <option value="Sulfonamides">
                                                <span>Sulfonamides</span><span class='text-sm text-gray-500 ml-1'>(trimethoprim/sulfamethoxazole)</span>
                                            </option>
                                            <option value="Fluoroquinolones">
                                                <span>Fluoroquinolones</span><span class='text-sm text-gray-500 ml-1'>(ciprofloxacin, levofloxacin, moxifloxacin)</span>
                                            </option>
                                            <option value="Macrolides">
                                                <span>Macrolides</span><span class='text-sm text-gray-500 ml-1'>(azithromycin, clarithromycin, erythromycin)</span>
                                            </option>
                                            <option value="Tetracyclines">
                                                <span>Tetracyclines</span><span class='text-sm text-gray-500 ml-1'>(doxycycline, minocycline, tetracycline)</span>
                                            </option>
                                            <option value="Aminoglycosides">
                                                <span>Aminoglycosides</span><span class='text-sm text-gray-500 ml-1'>(gentamicin, tobramycin, amikacin)</span>
                                            </option>
                                            <option value="Lincosamides">
                                                <span>Lincosamides</span><span class='text-sm text-gray-500 ml-1'>(clindamycin)</span>
                                            </option>
                                            <option value="Glycopeptides">
                                                <span>Glycopeptides</span><span class='text-sm text-gray-500 ml-1'>(vancomycin)</span>
                                            </option>
                                            <option value="Oxazolidinones">
                                                <span>Oxazolidinones</span><span class='text-sm text-gray-500 ml-1'>(linezolid)</span>
                                            </option>
                                            <option value="Nitroimidazoles">
                                                <span>Nitroimidazoles</span><span class='text-sm text-gray-500 ml-1'>(metronidazole)</span>
                                            </option>
                                            <option value="Nitrofurans">
                                                <span>Nitrofurans</span><span class='text-sm text-gray-500 ml-1'>(nitrofurantoin)</span>
                                            </option>
                                            <option value="Rifamycins">
                                                <span>Rifamycins</span><span class='text-sm text-gray-500 ml-1'>(rifampin)</span>
                                            </option>
                                        </select>
                                        <select id="severity" data-placeholder="Select severity level">
                                            <option value="mild" selected>Mild (e.g., rash, mild GI upset)</option>
                                            <option value="severe">Severe (e.g., anaphylaxis, Stevens-Johnson)</option>
                                        </select>
                                    </div>

                                    <div class="space-y-4 p-4 bg-blue-50 border rounded-lg shadow-card">
                                        <div class="flex items-center gap-2 mb-3"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-activity h-5 w-5 text-blue-600" aria-hidden="true">
                                                <path d="M22 12h-2.48a2 2 0 0 0-1.93 1.46l-2.35 8.36a.25.25 0 0 1-.48 0L9.24 2.18a.25.25 0 0 0-.48 0l-2.35 8.36A2 2 0 0 1 4.49 12H2"></path>
                                            </svg>
                                            <h3 class="font-medium text-blue-900">Renal Clearance</h3>
                                        </div>
                                        <select id="renal">
                                            <option value="">Select</option>
                                            <option value="normal">Renal CrCl &gt;30 / Normal</option>
                                            <option value="moderate">Renal CrCl 10-30</option>
                                            <option value="severe">Renal CrCl ≤10</option>
                                            <option value="dialysis">Dialysis</option>
                                        </select>
                                    </div>

                                    <input type="hidden" id="assen" name="assend" />
                                    <div class="flex gap-3">
                                        <button id="btn-send" data-slot="button"
                                            class="inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg:not([class*='size-'])]:size-4 shrink-0 [&amp;_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive h-9 py-2 has-[&gt;svg]:px-3 bg-primary hover:bg-primary/90 text-white px-6"
                                            type="submit"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-funnel h-4 w-4" aria-hidden="true">
                                                <path d="M10 20a1 1 0 0 0 .553.895l2 1A1 1 0 0 0 14 21v-7a2 2 0 0 1 .517-1.341L21.74 4.67A1 1 0 0 0 21 3H3a1 1 0 0 0-.742 1.67l7.225 7.989A2 2 0 0 1 10 14z"></path>
                                            </svg> Apply filter</button>
                                        <button id="btn-reset" data-slot="button"
                                            class="inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&_svg]:pointer-events-none [&_svg:not([class*='size-'])]:size-4 shrink-0 [&_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive border bg-background text-foreground hover:bg-accent hover:text-accent-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 h-9 py-2 has-[>svg]:px-3 px-6"
                                            type="submit">Reset</button>
                                        <div style="padding:5px">
                                            <svg id="loading_form_contact" style="display:none" xmlns="http://www.w3.org/2000/svg" width="64"
                                                height="18" viewBox="0 0 64 18" aria-hidden="true" role="img"
                                                style="/* display: none; */">
                                                <circle cx="9" cy="9" r="6" fill="#6B7280">
                                                    <animate attributeName="opacity" values="0.2;1;0.2" dur="1s"
                                                        repeatCount="indefinite" begin="0s"></animate>
                                                </circle>
                                                <circle cx="32" cy="9" r="6" fill="#6B7280">
                                                    <animate attributeName="opacity" values="0.2;1;0.2" dur="1s"
                                                        repeatCount="indefinite" begin="0.15s"></animate>
                                                </circle>
                                                <circle cx="55" cy="9" r="6" fill="#6B7280">
                                                    <animate attributeName="opacity" values="0.2;1;0.2" dur="1s"
                                                        repeatCount="indefinite" begin="0.3s"></animate>
                                                </circle>
                                            </svg>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <div class="pt-4 pb-2 text-xs text-muted-foreground border-t">
                            <p><strong style="color:unset">Important:</strong> Applying filters adjusts antibiotic display logic but does not dictate care. igniteABX does not provide medical advice. Clinicians are solely responsible for diagnosis and treatment decisions. Patient information is handled per HIPAA requirements.</p>
                        </div>

                    </div>

                    <div class="col-span-1">
                        <div data-slot="card" class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card">
                            <div data-slot="card-header" class="@container/card-header grid auto-rows-min grid-rows-[auto_auto] items-start gap-1.5 px-6 pt-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6">
                                <h4 data-slot="card-title" class="leading-none">About Clinical Filters</h4>
                            </div>
                            <div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6 space-y-3 text-sm text-gray-600">
                                <p><strong>Pregnancy/Lactation Filter:</strong> Filters antibiotic recommendations based on FDA pregnancy categories and lactation safety profiles. The system will prioritize safer alternatives and flag medications with known risks.</p>
                                <p><strong>Allergy Filter:</strong> Excludes antibiotics from the selected classes and flags cross-reactive medications. Mild allergies hide medications with similar structure, while severe allergies hide entire class and all β-lactams if applicable.</p>
                                <p><strong>How it work:</strong> Select a report, choose your filter type, and configure the parameters. The filtered report will show modified antibiotic recommendations with safety warnings where appropriate.</p>
                                <p class="text-xs text-gray-500"><strong>Clinical guidance only:</strong> All recommendations must be reviewed by a qualified healthcare provider before implementation.</p>
                            </div>
                        </div>
                    </div>

                </div>

            </div>
        </div>
    </div>
</div>
<script>
    var $ajaxurl_search = '<?php echo Tr_Base_Class_V6::link(array('trajax' => 'search_report')) ?>';
    jQuery(function($) {
        window.$ = $;
        $('#report-select').autocomplete({
            source: $ajaxurl_search,
            minLength: 3,
            select: function(event, ui) {
                if (ui.item) {
                    $("#assen").val(ui.item.assen);
                }
            }
        });
        $("#btn-send").click(function() {
            $("#loading_form_contact").show();
            const assen = $("#assen").val();
            const antibiotic = $("#antibiotic").val();
            const severity = $("#severity").val();
            const renal = $("#renal").val();
            const Pregnancy = $("#pregnant-lactating").val();
            if (!assen) {
                showToast('info', 'Please select a report or enter patient information', '', 4000, 'top_center');
                $("#loading_form_contact").hide();
                return false;
            }

            var $url = '<?php echo Tr_Base_Class_V6::link(array('trajax' => 'apply_filter')) ?>';
            $url += '&assen=' + encodeURIComponent(assen);
            $url += '&antibiotic=' + encodeURIComponent(antibiotic) + '&severity=' + encodeURIComponent(severity);
            $url += '&pregnancy=' + encodeURIComponent(Pregnancy);
            $url += '&renal=' + encodeURIComponent(renal);
            window.opened = window.open($url, 'abx_filter_window');

            setTimeout(() => {
                $("#loading_form_contact").hide();
            }, 1000);

            return false;
        });

        $("#btn-reset").click(function(e) {
            e.preventDefault();
            refreshCustomSelect("pregnant-lactating");
            refreshCustomSelect("antibiotic");
            refreshCustomSelect("severity");
        });

        initCustomSelect("select");
    });
</script>