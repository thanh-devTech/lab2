<html>
<body>
<script src="<?php echo TRCPDD_URL?>/js/DYMO.Label.Framework.js" type="text/javascript" charset="UTF-8"></script>
<script src="<?php echo TRCPDD_URL?>/js/dymo-label.js" type="text/javascript" charset="UTF-8"></script>
<form>
	<p>Text: <input type="text" id="content" name="content" value="CMD01234567" /></p>
	<button type="button" onclick="ci_print()">Print</button>
</form>

<script>
    window.dymo_label.check_framework();

    function ci_print() {
        var assen = document.getElementById('content').value;
        console.log('print: '+assen);
        window.dymo_label.printbarcode( assen );
    }
</script>
</body>
</html>