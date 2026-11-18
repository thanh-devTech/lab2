    <?php
    $abx_tabs = array(
        'antibiotics' => 'Antibiotics + FDA approval',
        'bacteria' => 'Bacteria',
        'resistance_markers' => 'Resistance Markers',
        'resistance_markers_expression' => 'Resistance Markers expression in bacteria',
        
    );
    ?>
    <div class="wrap">
        <h2>ABX</h2>
        <?php
        echo '<h2 class="nav-tab-wrapper">';
        foreach($abx_tabs as $tk => $tv)
        {
            echo '<a class="nav-tab" href="">'.$tv.'</a>';
        }
        echo '</h2>';
        ?>
    </div>