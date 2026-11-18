window.dymo_label = {

	templates: {},

	check_framework: function(){

		if ( !dymo || !dymo.label || !dymo.label.framework ) {
			return [false,'Can`t print label: DYMO Framework is not loaded.'];
		}

		var printers = dymo.label.framework.getPrinters();

		if ( !printers || printers.length === 0 ) {
			return [false,'Can`t print label: no label printers found.'];
		}

		var check = dymo.label.framework.checkEnvironment();

		if ( !check.isBrowserSupported ) {
			return [false,'DYMO Framework: your browser is not supported.'];
		}

		if ( !check.isFrameworkInstalled ) {
			return [false,'DYMO Framework is not installed.'];
		}

		if ( !check.isWebServicePresent ) {
			return [false,'DYMO Framework: web service is not present.'];
		}

		return true;

	},

	load_template: function( url, success ){
		
		// if loaded
		if( this.templates[url] ){
			success(this.templates[url]);
			return this.templates[url];
		}

		// if not loaded
		var self = this;
		var xhttp = new XMLHttpRequest();
		xhttp.onreadystatechange = function() {
			if ( this.readyState == 4 && this.status == 200 ) {
				self.templates[url] = this.response;
				success(this.response);
			}
		};
		xhttp.open('GET', url, true);
		xhttp.send();

		return xhttp;
		
	},

	printbarcode: function (assen) {

		if (!dymo)
		{
			return;
		}
        var labelXml = '<?xml version="1.0" encoding="utf-8"?>\
        <DieCutLabel Version="8.0" Units="twips">\
            <PaperOrientation>Landscape</PaperOrientation>\
            <Id>Address</Id>\
            <PaperName>30252 Address</PaperName>\
            <DrawCommands/>\
            <ObjectInfo>\
                <TextObject>\
                    <Name>Text</Name>\
                    <ForeColor Alpha="255" Red="0" Green="0" Blue="0" />\
                    <BackColor Alpha="0" Red="255" Green="255" Blue="255" />\
                    <LinkedObjectName></LinkedObjectName>\
                    <Rotation>Rotation0</Rotation>\
                    <IsMirrored>False</IsMirrored>\
                    <IsVariable>True</IsVariable>\
                    <HorizontalAlignment>Left</HorizontalAlignment>\
                    <VerticalAlignment>Middle</VerticalAlignment>\
                    <TextFitMode>ShrinkToFit</TextFitMode>\
                    <UseFullFontHeight>True</UseFullFontHeight>\
                    <Verticalized>False</Verticalized>\
                    <StyledText/>\
                </TextObject>\
                <Bounds X="332" Y="150" Width="4455" Height="1260" />\
            </ObjectInfo>\
            <ObjectInfo>\
             <BarcodeObject>\
                 <Name>Barcode</Name>\
                 <ForeColor Alpha="255" Red="0" Green="0" Blue="0" />\
                 <BackColor Alpha="0" Red="255" Green="255" Blue="255" />\
                 <LinkedObjectName>BarcodeText</LinkedObjectName>\
                 <Rotation>Rotation0</Rotation>\
                 <IsMirrored>False</IsMirrored>\
                 <IsVariable>True</IsVariable>\
                 <Text>barCode</Text>\
                 <Type>Code128Auto</Type>\
                 <Size>Medium</Size>\
                 <TextPosition>Bottom</TextPosition>\
                 <TextFont Family="Arial" Size="8" Bold="False" Italic="False" Underline="False" Strikeout="False" />\
                 <CheckSumFont Family="Arial" Size="8" Bold="False" Italic="False" Underline="False" Strikeout="False" />\
                 <TextEmbedding>None</TextEmbedding>\
                 <ECLevel>0</ECLevel>\
                 <HorizontalAlignment>Center</HorizontalAlignment>\
                 <QuietZonesPadding Left="0" Top="0" Right="0" Bottom="0" />\
             </BarcodeObject>\
             <Bounds X="324" Y="950" Width="3150" Height="520" />\
         </ObjectInfo>\
        </DieCutLabel>';

        var label = dymo.label.framework.openLabelXml(labelXml);

        // set label text
        label.setObjectText("Text", assen);
        label.setObjectText('Barcode', assen);
        // select printer to print on
        // for simplicity sake just use the first LabelWriter printer
        var printers = dymo.label.framework.getPrinters();
        // Gets name of first printer
        var printer_name = printers[0].name;

        label.print(printerName);
    },

	print: function( label, number_of_copies ) {

		if( !dymo ){
			return null;
		}

		var value = document.getElementById("content").value;
		label.setObjectText('Text', value);

		var printers = dymo.label.framework.getPrinters();
		// Gets name of first printer
		var printer_name = printers[0].name;

		if( printer_name && label ){

			var print_params = dymo.label.framework.createLabelWriterPrintParamsXml({
				copies: number_of_copies
			});

			label.print( printer_name, print_params );

			return label;

		}

		return null;
	}

};