<?php
$class = 'dompdf';
if (isset($_GET['testpdf'])) {
    $class .= ' testpdf';
}
if (isset($_GET['testhtml'])) {
    $class = 'testhtml';
}
?>
<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8" />
    <title><?php echo (isset($title) && !empty($title)) ? $title : 'PDF' ?></title>
    <style>
        @page {
            margin: 0.8cm 0.6cm 0.8cm 0.6cm;
        }

        body {
            font-family: "DejaVu Sans", Arial, Helvetica, sans-serif;
            font-size: 8pt;
            margin: 0;
            padding: 0;
        }

        h2 {
            font-size: 16px;
            font-weight: bold;
            margin-top: 0;
        }

        .center {
            text-align: center;
        }

        .right {
            text-align: right;
        }

        .bold {
            font-weight: bold;
        }

        .break {
            page-break-after: always;
            border: 0;
        }

        table {
            width: 100%;
            max-width: 100%;
            border-spacing: 0px;
            page-break-inside: auto;
            border-collapse: collapse;
        }

        table th {
            text-align: left;
            padding: 5px;
        }

        table td {
            padding: 5px;
        }

        table.minifont td,
        table.minifont th {
            font-size: 5pt;
        }

        table.tdborder td,
        table.tdborder th {
            border: 1px solid #aaa;
        }

        #header,
        #footer {
            position: fixed;
            left: 0;
            right: 0;
            color: #666;
            font-size: 0.9em;
        }

        #header {
            top: 0;
        }

        .notprint {
            display: none;
        }

        #footer {
            bottom: 5px;
            border-top: 0.1pt solid #aaa;
            line-height: 13px;
            padding-top: 5px;
        }
    </style>
</head>

<body class=" <?php echo $class ?>">



    <?php echo $content ?>

    <?php
    if (!isset($pagination) || $pagination) {
        include('_pdf_pagination.php');
    }

    ?>
</body>

</html>