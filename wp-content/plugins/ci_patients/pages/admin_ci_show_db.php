<?php
global $wpdb;
$cur_page="page=ci_show_db";
$type_page = isset($_REQUEST['type_page']) ? $_REQUEST['type_page'] : 'list_table'; //list_table,show_table,sql_cmd
?>
<link rel="stylesheet" href="<?php echo plugin_dir_url(__FILE__) . '../css/admin_show_db.css'; ?>">
<div class="tab-main">
  <a href="?page=ci_show_db&type_page=list_table" 
     class="<?php echo ($type_page == 'list_table') ? 'active' : ''; ?>">
     List Table
  </a>
  <a href="?page=ci_show_db&type_page=sql_cmd" 
     class="<?php echo ($type_page == 'sql_cmd') ? 'active' : ''; ?>">
     SQL Command
  </a>
  <a href="?page=ci_show_db&type_page=statistics" 
     class="<?php echo ($type_page == 'statistics') ? 'active' : ''; ?>">
     Statistics
  </a>
</div>
<?php
    if($type_page=='list_table'){
      $tables = $wpdb->get_results( "SHOW TABLE STATUS" );
?>
<div class="db-search">
  <input type="search" id="db-table-filter" placeholder="Search tables..." aria-label="Filter tables">
  <span class="badge">Tables: <?php echo is_array($tables) ? count($tables) : 0; ?></span>
</div>

<table class="wp-db-table-list tlist" id="wp-db-table-list">
  <thead>
    <tr>
      <th>Table Name</th>
      <th>Rows</th>
      <th>Size (MB)</th>
      <th>Engine</th>
      <th>Created</th>
      <th>Actions</th>
    </tr>
  </thead>
  <tbody>
  <?php
  if ( ! empty( $tables ) ) {
    foreach ( $tables as $t ) {
      $name = isset($t->Name) ? $t->Name : (isset($t->name) ? $t->name : '');
      $rows = isset($t->Rows) ? $t->Rows : (isset($t->rows) ? $t->rows : 0);
      $data_len = isset($t->Data_length) ? $t->Data_length : 0;
      $index_len = isset($t->Index_length) ? $t->Index_length : 0;
      $engine = isset($t->Engine) ? $t->Engine : (isset($t->engine) ? $t->engine : '');
      $create_time = isset($t->Create_time) ? $t->Create_time : (isset($t->create_time) ? $t->create_time : '');

      $size_mb = ($data_len + $index_len) / 1024 / 1024;
      $size_str = number_format( (float) $size_mb, 3 );

      $safe_name = str_replace('`', '``', $name);
      $create_row = $wpdb->get_row( "SHOW CREATE TABLE `{$safe_name}`", ARRAY_A );
      $create_sql = '';
      if ( $create_row ) {
        if ( isset( $create_row['Create Table'] ) ) {
          $create_sql = $create_row['Create Table'];
        } else {
          $vals = array_values( $create_row );
          $create_sql = end( $vals );
        }
      }
      ?>
      <tr data-table="<?php echo esc_attr( $name ); ?>">
        <td><strong><?php echo esc_html( $name ); ?></strong></td>
        <td><?php echo number_format_i18n( (int) $rows ); ?></td>
        <td><?php echo $size_str; ?></td>
        <td><?php echo esc_html( $engine ); ?></td>
        <td><?php echo esc_html( $create_time ); ?></td>
        <td>
          <button class="toggle-create" data-target="<?php echo esc_attr( 'create-' . md5( $name ) ); ?>">Show CREATE</button>
          <a class="show-data-btn" href="?<?php echo $cur_page;?>&type_page=show_table&table_id=<?php echo $name; ?>">Show Data</a>
        </td>
      </tr>
      <tr class="create-row" id="<?php echo esc_attr( 'create-' . md5( $name ) ); ?>">
        <td colspan="6">
          <pre class="create-sql"><?php echo esc_html( $create_sql ); ?></pre>
        </td>
      </tr>
    <?php
    }
  } else {
    echo '<tr><td colspan="6">No tables found.</td></tr>';
  }
  ?>
  </tbody>
</table>

<script>
(function(){
  // Toggle CREATE SQL
  document.querySelectorAll('.toggle-create').forEach(function(btn){
    btn.addEventListener('click', function(e){
      e.preventDefault();
      var id = btn.getAttribute('data-target');
      if(!id) return;

      var tr = document.getElementById(id);
      if(!tr) return;

      if(tr.style.display === '' || tr.style.display === 'none') {
        tr.style.display = 'table-row';
        btn.textContent = 'Hide CREATE';
      } else {
        tr.style.display = 'none';
        btn.textContent = 'Show CREATE';
      }
    });
  });

  // Filter tables
  var input = document.getElementById('db-table-filter');
  input.addEventListener('input', function(e){
    var q = e.target.value.toLowerCase();
    document.querySelectorAll('#wp-db-table-list tbody tr').forEach(function(row){
      if(row.classList.contains('create-row')) return;
      var name = row.getAttribute('data-table') || '';
      if(name.toLowerCase().indexOf(q) !== -1) {
        row.style.display = '';
      } else {
        row.style.display = 'none';
        var id = 'create-' + md5(name);
        var createRow = document.getElementById(id);
        if(createRow) createRow.style.display = 'none';
      }
    });
  });

  function md5(str) {
    var h = 0;
    for (var i = 0; i < str.length; i++) {
      h = ((h << 5) - h) + str.charCodeAt(i);
      h |= 0;
    }
    return 'm' + (h >>> 0).toString(16);
  }

})();
</script>
<?php }?>
    
<?php 
if($type_page=='show_table'){ 
  $table_id = isset($_REQUEST['table_id']) ? $_REQUEST['table_id'] : '';
  $paged    = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
  $per_page = 20;
  $offset   = ($paged - 1) * $per_page;
  $cur_page = add_query_arg('page', 'ci_show_db', admin_url('admin.php'));
?>
    <div class="db-search">
        <a href="?page=ci_show_db&type_page=list_table" class="button-primary">Back</a>
    </div>
    <div class="wrap">
        <h2>Data in table: <?php echo esc_html($table_id); ?></h2>
        <?php
        $total_rows = (int) $wpdb->get_var("SELECT COUNT(*) FROM `$table_id`");
        $rows = $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM `$table_id` LIMIT %d OFFSET %d", $per_page, $offset),
            ARRAY_A
        );

        if ($rows): ?>
            <table class="wp-db-table-list">
                <thead>
                    <tr>
                        <?php foreach (array_keys($rows[0]) as $col): ?>
                            <th><?php echo esc_html($col); ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <?php foreach ($row as $val): ?>
                                <td><?php echo esc_html($val); ?></td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <?php
            // Tổng số trang
            $total_pages = ceil($total_rows / $per_page);

            if ($total_pages > 1): ?>
                <div class="tablenav-pages">
                    <?php
                    // Previous
                    if ($paged > 1) {
                        echo '<a class="button" href="' . esc_url(add_query_arg([
                            'type_page'=> $type_page,
                            'table_id' => $table_id,
                            'paged'    => $paged - 1,
                        ], $cur_page)) . '">« Prev</a> ';
                    }

                    // Numbered pages
                    for ($i = 1; $i <= $total_pages; $i++) {
                        $class = $i == $paged ? 'button-primary' : 'button';
                        echo '<a class="' . $class . '" href="' . esc_url(add_query_arg([
                            'type_page'=> $type_page,
                            'table_id' => $table_id,
                            'paged'    => $i,
                        ], $cur_page)) . '">' . $i . '</a> ';
                    }

                    // Next
                    if ($paged < $total_pages) {
                        echo '<a class="button" href="' . esc_url(add_query_arg([
                            'type_page'=> $type_page,
                            'table_id' => $table_id,
                            'paged'    => $paged + 1,
                        ], $cur_page)) . '">Next »</a>';
                    }
                    ?>
                </div>
            <?php endif; ?>

        <?php else: ?>
            <p><em>No data found in this table.</em></p>
        <?php endif; ?>
    </div>
<?php }?>

<?php 
if($type_page=='sql_cmd'){

$delete_history=isset($_REQUEST['delete_history']) ? $_REQUEST['delete_history'] : '';
$current_user_id = get_current_user_id();
$history_key = 'sql_console_history_' . $current_user_id;

$sql_result = null;
$sql_error  = '';
if($delete_history!=""){
  if($delete_history=="all"){
    update_option($history_key, []);
  }
}

$sql_history = get_option($history_key, []);

if ( current_user_can('manage_options') && !empty($_POST['custom_sql']) ) {
    $custom_sql = trim(stripslashes($_POST['custom_sql']));
    if ( stripos($custom_sql, 'select') === 0 ) {
      $sql_result = $wpdb->get_results($custom_sql, ARRAY_A);
    } else {
      $rows = $wpdb->query($custom_sql);
      $sql_result = "Query executed. Affected rows: " . intval($rows);
    }

    array_unshift($sql_history, [
        'sql' => $custom_sql,
        'time' => current_time('mysql')
    ]);

    $sql_history = array_slice($sql_history, 0, 10);
    update_option($history_key, $sql_history);

    if ( !empty($wpdb->last_error) ) {
        $sql_error = $wpdb->last_error;
    }
}
?>
<!-- CodeMirror CSS -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.15/codemirror.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.15/theme/material.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.15/addon/hint/show-hint.min.css">

<!-- CodeMirror JS -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.15/codemirror.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.15/mode/sql/sql.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.15/addon/hint/show-hint.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.15/addon/hint/sql-hint.min.js"></script>

<div class="sql-console">
  <h2>Run SQL Command</h2>
  <form method="post">
    <textarea name="custom_sql" id="sql_command"  rows="5" style="width:100%;" placeholder="Enter SQL command here..."><?php echo isset($_POST['custom_sql']) ? esc_textarea($_POST['custom_sql']) : ''; ?></textarea>
    <br>
    <button type="submit" class="button button-primary">Execute</button>
    <i><kbd>Ctrl-Space</kbd> To display table and column hints</i>
  </form>

  <?php if ( $sql_error ): ?>
    <div class="sql-error">Error: <?php echo esc_html($sql_error); ?></div>
  <?php endif; ?>

  <?php if ( $sql_result ): ?>
    <div class="sql-result">
      <?php if ( is_string($sql_result) ): ?>
        <p><?php echo esc_html($sql_result); ?></p>
      <?php elseif ( !empty($sql_result) ): ?>
        <table class="wp-db-table-list">
          <thead>
            <tr>
              <?php foreach (array_keys($sql_result[0]) as $col): ?>
                <th><?php echo esc_html($col); ?></th>
              <?php endforeach; ?>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($sql_result as $row): ?>
              <tr>
                <?php foreach ($row as $val): ?>
                  <td><?php echo esc_html($val); ?></td>
                <?php endforeach; ?>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <?php if ( !empty($sql_history) ): ?>
    <div class="sql-history">
      <h3>SQL History</h3>
      <a href="?page=ci_show_db&type_page=sql_cmd&delete_history=all">Clear All</a>
      <ul>
        <?php foreach ( $sql_history as $item ): ?>
          <li>
            <code><?php echo esc_html($item['sql']); ?></code>
            <small>(<?php echo esc_html($item['time']); ?>)</small>
            <a href="#" class="sql-history-item" data-sql="<?php echo esc_attr($item['sql']); ?>">Used</a>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>
</div>
<script>
document.addEventListener("DOMContentLoaded", function () {
  var editor = CodeMirror.fromTextArea(document.getElementById("sql_command"), {
    mode: "text/x-sql",
    theme: "material",
    lineNumbers: true,
    extraKeys: {"Ctrl-Space": "autocomplete"}
  });

  var schema = <?php
    global $wpdb;
    $tables = $wpdb->get_col("SHOW TABLES");
    $schema = [];
    foreach ($tables as $table) {
        $cols = $wpdb->get_col("SHOW COLUMNS FROM $table");
        $schema[$table] = $cols;
    }
    echo json_encode($schema);
  ?>;

  CodeMirror.registerHelper("hint", "sql", function(editor) {
    var cur = editor.getCursor();
    var token = editor.getTokenAt(cur);
    var start = token.start;
    var end = token.end;
    var word = token.string;
    var result = [];

    if (word.length > 0) {
      Object.keys(schema).forEach(function(tbl) {
        if (tbl.toLowerCase().indexOf(word.toLowerCase()) === 0) result.push(tbl);
        schema[tbl].forEach(function(col) {
          if (col.toLowerCase().indexOf(word.toLowerCase()) === 0) result.push(col);
        });
      });
    }

    return {
      list: result,
      from: CodeMirror.Pos(cur.line, start),
      to: CodeMirror.Pos(cur.line, end)
    };
  });

    document.querySelectorAll('.sql-history-item').forEach(function(item){
        item.addEventListener('click', function(e){
            e.preventDefault();
            let sql = this.getAttribute('data-sql');
            editor.setValue(sql);
            editor.setCursor(editor.lineCount(), 0);
            editor.focus();
        });
    });

  document.querySelector('form').addEventListener('submit', function() {
    sqlEditor.save();
  });
});
</script>
<?php }?>

<?php
if($type_page=='statistics'){
$tables = $wpdb->get_results("SHOW TABLE STATUS");

$total_size = 0;
$total_rows = 0;
$data = array();

if ( ! empty( $tables ) ) {
    foreach ( $tables as $t ) {
        $name  = $t->Name;
        $rows  = isset($t->Rows) ? $t->Rows : 0;
        $size  = ($t->Data_length + $t->Index_length) / 1024 / 1024; // MB

        $total_size += $size;
        $total_rows += $rows;

        $data[] = array(
            'name' => $name,
            'rows' => $rows,
            'size' => $size,
        );
    }
}

usort($data, function($a, $b) {
    return $b['size'] <=> $a['size'];
});

$top5 = array_slice($data, 0, 5);
?>
<link rel="stylesheet" href="<?php echo plugin_dir_url(__FILE__) . '../css/admin_show_db.css'; ?>">

<div class="db-stats">
  <h2>Database Statistics</h2>

  <div class="db-stats-row">
    <div class="db-stats-info">
      <p><strong>Total Tables:</strong> <?php echo count($tables); ?></p>
      <p><strong>Total Rows:</strong> <?php echo number_format_i18n($total_rows); ?></p>
      <p><strong>Total Size:</strong> <?php echo number_format_i18n($total_size, 2); ?> MB</p>
    </div>

    <div class="db-stats-chart">
      <canvas id="dbChart" width="250" height="250"></canvas>
    </div>
  </div>

  <h3>Top 5 Largest Tables</h3>
  <table class="wp-db-table-list">
    <thead>
      <tr>
        <th>Table</th>
        <th>Rows</th>
        <th>Size (MB)</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($top5 as $t): ?>
        <tr>
          <td><?php echo esc_html($t['name']); ?></td>
          <td><?php echo number_format_i18n($t['rows']); ?></td>
          <td><?php echo number_format_i18n($t['size'], 2); ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
(function(){
  const ctx = document.getElementById('dbChart').getContext('2d');
  const chart = new Chart(ctx, {
    type: 'pie',
    data: {
      labels: <?php echo json_encode(array_column($top5, 'name')); ?>,
      datasets: [{
        label: 'Table Size (MB)',
        data: <?php echo json_encode(array_column($top5, 'size')); ?>,
        backgroundColor: [
          '#3b82f6',
          '#10b981',
          '#f59e0b',
          '#ef4444',
          '#8b5cf6'
        ],
      }]
    },
    options: {
      responsive: true,
      plugins: {
        legend: { position: 'bottom' },
      }
    }
  });
})();
</script>
<?php }?>