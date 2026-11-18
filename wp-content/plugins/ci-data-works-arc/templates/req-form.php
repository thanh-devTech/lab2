<?php

/**
 * Lab Requisition Form Template
 * 
 * This template is rendered inside DrChrono iFrame
 * 
 * Available variables:
 * @var array $patient   Patient data from DrChrono API
 * @var array $doctor    Doctor data from DrChrono API
 * @var int   $doctor_id
 * @var int   $patient_id
 * @var int   $practice_id
 */

defined('ABSPATH') || exit;

// Patient info
$patient_name = trim(($patient['first_name'] ?? '') . ' ' . ($patient['last_name'] ?? ''));
$patient_dob  = $patient['date_of_birth'] ?? '';
$patient_sex  = $patient['gender'] ?? '';
$patient_mrn  = $patient['chart_id'] ?? '';

// Doctor info
$doctor_name = trim(($doctor['first_name'] ?? '') . ' ' . ($doctor['last_name'] ?? ''));
$doctor_npi  = $doctor['npi_number'] ?? '';

$nonce = wp_create_nonce('drchrono_lab_nonce');

// Get lab configuration
$lab_instance = DrChrono_Lab::get_instance();
$lab_name     = $lab_instance->get_option('lab_name', 'Lab Services');
$btypes = tr_pdd_get_batch_types();
$acceptbtypes = ['pcr', 'tox', 'blood'];
$acceptbtypes = array_intersect($acceptbtypes, array_values($btypes));

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lab Requisition Form <?php echo $reqtype; ?></title>
    <?php wp_head(); ?>
    <style>
        .form-select {
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;

            padding: 10px 36px 10px 12px;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            background-color: #fff;
            font-size: 14px;
            font-weight: 500;
            color: #334155;
            cursor: pointer;
            transition: all 0.2s;
            font-family: inherit;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' stroke='%23647569' stroke-width='2' viewBox='0 0 24 24'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 10px center;
            background-size: 16px;
        }

        /* hover */
        .form-select:hover {
            border-color: #cbd5f5;
        }

        .form-select:focus {
            outline: none;
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.2);
        }

        .form-select:disabled {
            background-color: #f8fafc;
            cursor: not-allowed;
            opacity: 0.7;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            font-family: inherit;
            white-space: nowrap;
        }

        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .btn--primary {
            background: #2563eb;
            color: #fff;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
        }

        .btn--primary:hover:not(:disabled) {
            background: #1d4ed8;
            box-shadow: 0 4px 6px rgba(37, 99, 235, 0.2);
        }

        .btn--secondary {
            background: #f1f5f9;
            color: #475569;
            border: 1px solid #e2e8f0;
        }

        .btn--secondary:hover:not(:disabled) {
            background: #e2e8f0;
        }

        .btn__icon {
            width: 16px;
            height: 16px;
        }

        /* Loading Spinner */
        .spinner {
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-top-color: #fff;
            border-radius: 50%;
            animation: spin 0.6s linear infinite;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }
    </style>
</head>

<body>
    <?php if ($reqtype === '') { ?>
        <div style="display:flex; flex-direction:column; align-items:center; justify-content:center; min-height:200px; gap:12px;">
            <label for="reqtype-select" style="font-size:13px; font-weight:600; text-transform:uppercase; letter-spacing:0.05em; color:#64748b;">
                Choose req form
            </label>
            <div style="display:flex; gap:10px; align-items:center;">
                <select id="reqtype-select" class="form-select" style="width:200px;">
                    <option value="">-- Select type --</option>
                    <?php foreach ($acceptbtypes as $bt): ?>
                        <option value="<?php echo esc_attr($bt); ?>"><?php echo tr_pdd_get_typename($bt); ?></option>
                    <?php endforeach; ?>
                </select>
                <button id="btn-go" class="btn btn--primary">
                    <svg class="btn__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="9 18 15 12 9 6" />
                    </svg>
                    Go
                </button>
            </div>
        </div>
    <?php } else { ?>
        <button type="button" class="btn btn--secondary" id="btn-back">
            <svg class="btn__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="15 18 9 12 15 6" />
            </svg>
            Back
        </button>
    <?php
        include TRCPDD_PATH . 'templates/' . $reqtype . '-form.php';
    } ?>

    <script>
        var btnBack = document.getElementById('btn-back');
        (function() {

            const reqSelect = document.getElementById('reqtype-select');
            const btnGo = document.getElementById('btn-go');

            if (btnGo) {
                btnGo.addEventListener('click', function() {
                    const val = reqSelect ? reqSelect.value : '';
                    if (!val) {
                        alert('Please select a form type.');
                        return;
                    }
                    btnGo.disabled = true;
                    btnGo.innerHTML = '<span class="spinner"></span> Loading...';

                    const url = new URL(window.location.href);
                    url.searchParams.set('reqtype', val);
                    window.location.href = url.toString();
                });
            }
            if (btnBack) {
                btnBack.addEventListener('click', function() {
                    const url = new URL(window.location.href);
                    url.searchParams.delete('reqtype');
                    btnBack.disabled = true;
                    btnBack.innerHTML = '<span class="spinner"></span> Loading...';
                    window.location.href = url.toString();
                });
            }

        })();
    </script>
</body>

</html>