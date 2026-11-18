
var dbConn;
var serializer = SerializerFactory.getSerializer('HL7V2');
var ack = new XML(serializer.toXML(ACKGenerator.generateAckResponse(connectorMessage.getEncodedData() ,'AA','')));


var formatter = new Packages.java.text.SimpleDateFormat("yyyy-MM-dd HH:mm:ss");
formatter.setTimeZone(Packages.java.util.TimeZone.getTimeZone("US/Central"));
var $created = formatter.format(new Packages.java.util.Date());

try {

    dbConn = DatabaseConnectionFactory.createDatabaseConnection('com.mysql.cj.jdbc.Driver','jdbc:mysql://db:3306/ARK_Laboratory?serverTimezone=UTC','instrument','ui45rre32');


    var xml = new XML(serializer.toXML(connectorMessage.getEncodedData() ));
    var msh = xml.elements("MSH");
    var msgType = msh["MSH.9"]["MSH.9.1"];
    //logger.info("msgType: "+msgType);

    ack["MSH"] = msh;

    ack["MSH"]["MSH.2"]["MSH.2.1"] = msh["MSH.4"]["MSH.4.1"];
    ack["MSH"]["MSH.4"]["MSH.4.1"] = msh["MSH.2"]["MSH.2.1"];

    ack["MSA"]["MSA.2"]["MSA.2.1"] = msh["MSH.10"]["MSH.10.1"];
    ack["MSA"]["MSA.3"]["MSA.3.1"] = "";
    ack["MSA"]["MSA.4"]["MSA.4.1"] = "";
    ack["MSA"]["MSA.5"]["MSA.5.1"] = "";
    ack["MSA"]["MSA.6"]["MSA.6.1"] = "0";
    ack["MSA"]["MSA.7"]["MSA.7.1"] = "";

    if(msgType=="QRY")
    {

        ack["MSH"]["MSH.9"]["MSH.9.1"] = "DSR";
        ack["MSH"]["MSH.9"]["MSH.9.2"] = "Q03";
        ack["MSA"]["MSA.1"]["MSA.1.1"] = "OK";

        var qrd = xml.elements("QRD");
        var $barcode = qrd["QRD.8"]["QRD.8.1"].toString();
        var sample_no = qrd["QRD.4"]["QRD.4.1"].toString();
        var rack_no = qrd["QRD.10"]["QRD.10.1"].toString();
        var position = qrd["QRD.10"]["QRD.10.2"].toString();


        createSegment('DSP',ack,0);
        ack["DSP"][0]["DSP.1"]["DSP.1.1"] = "1";
        ack["DSP"][0]["DSP.2"]["DSP.2.1"] = "";
        ack["DSP"][0]["DSP.3"]["DSP.3.1"] = $barcode;
        ack["DSP"][0]["DSP.4"]["DSP.4.1"] = "";
        ack["DSP"][0]["DSP.5"]["DSP.5.1"] = $barcode;
        ack["DSP"][0]["DSP.6"]["DSP.6.1"] = "";
        ack["DSP"][0]["DSP.6"]["DSP.6.2"] = "";
        ack["DSP"][0]["DSP.7"]["DSP.7.1"] = "";
        ack["DSP"][0]["DSP.8"]["DSP.8.1"] = "O";
        ack["DSP"][0]["DSP.29"]["DSP.29.1"] = "";

        createSegment('DSP',ack,1);
        ack["DSP"][1]["DSP.1"]["DSP.1.1"] = "2";
        ack["DSP"][1]["DSP.2"]["DSP.2.1"] = $barcode;
        ack["DSP"][1]["DSP.3"]["DSP.3.1"] = "";

        ack["DSP"][1]["DSP.5"]["DSP.4.1"] = ack["MSH"]["MSH.3"]["MSH.3.1"];
        ack["DSP"][1]["DSP.5"]["DSP.4.2"] = ack["MSH"]["MSH.4"]["MSH.4.1"];

        ack["DSP"][1]["DSP.5"]["DSP.5.1"] = "N";
        ack["DSP"][1]["DSP.6"]["DSP.6.1"] = "";
        ack["DSP"][1]["DSP.7"]["DSP.7.1"] = ack["MSH"]["MSH.7"]["MSH.7.1"];
        ack["DSP"][1]["DSP.8"]["DSP.8.1"] = "";
        ack["DSP"][1]["DSP.9"]["DSP.9.1"] = "1";
        ack["DSP"][1]["DSP.10"]["DSP.10.1"] = rack_no;
        ack["DSP"][1]["DSP.10"]["DSP.10.2"] = position;
        ack["DSP"][1]["DSP.11"]["DSP.11.1"] = "";
        ack["DSP"][1]["DSP.12"]["DSP.12.1"] = "N";
        ack["DSP"][1]["DSP.13"]["DSP.13.1"] = "";
        ack["DSP"][1]["DSP.14"]["DSP.14.1"] = ack["MSH"]["MSH.7"]["MSH.7.1"];
        ack["DSP"][1]["DSP.15"]["DSP.15.1"] = "0";
        ack["DSP"][1]["DSP.16"]["DSP.16.1"] = "";
        ack["DSP"][1]["DSP.17"]["DSP.17.1"] = "";
        ack["DSP"][1]["DSP.18"]["DSP.18.1"] = "";
        ack["DSP"][1]["DSP.19"]["DSP.19.1"] = "";
        ack["DSP"][1]["DSP.20"]["DSP.20.1"] = "";
        ack["DSP"][1]["DSP.21"]["DSP.21.1"] = "";
        ack["DSP"][1]["DSP.22"]["DSP.22.1"] = "";
        ack["DSP"][1]["DSP.23"]["DSP.23.1"] = "";

        createSegment('DSP',ack,2);
        ack["DSP"][2]["DSP.1"]["DSP.1.1"] = "3";
        ack["DSP"][2]["DSP.2"]["DSP.2.1"] = 0;

        var $count_test = 0;


        try{
            var newresult = dbConn.executeCachedQuery("select mid from ci_batches where assen='"+$barcode+"'");
            if(newresult.next())
            {
                var mid = newresult.getInt(1);
                //logger.info("QRY - barcode: "+$barcode+" mid: "+mid);


                var uriners = dbConn.executeCachedQuery("select u_abreviation_mc6410 from ci_urine_validity where u_abreviation_mc6410 is not null " +
                    " and u_abreviation_mc6410!=''" +
                    " and u_showinreq=0 and u_status = 1");
                while(uriners.next())
                {
                    try{
                        var u_abreviation = uriners.getString(1);

                        $count_test++;
                        ack["DSP"][2]["DSP.3"]["DSP.3."+$count_test] = u_abreviation;
                    }catch (e){
                        logger.error(e);
                    }
                }

                var screenings = dbConn.executeCachedQuery("select u.u_abreviation_mc6410 from ci_urine_validity u " +
                    " join ci_result_test_speciments t ON t.testid = u.u_ID and t.checked = 1" +
                    " where u.u_abreviation_mc6410 is not null and u.u_abreviation_mc6410!=''" +
                    " and u.u_showinreq=1 and u.u_status = 1" +
                    " and t.mid = "+mid);
                while(screenings.next())
                {
                    try{
                        var u_abreviation = screenings.getString(1);

                        $count_test++;
                        ack["DSP"][2]["DSP.3"]["DSP.3."+$count_test] = u_abreviation;
                    }catch (e){
                        logger.error(e);
                    }
                }

            }else{
                logger.info("QRY - barcode: "+$barcode+" not exists");
            }
        }catch (e){
            logger.error(e);
        }


        ack["DSP"][2]["DSP.2"]["DSP.2.1"] = $count_test;
        ack["DSP"][2]["DSP.4"]["DSP.4.1"] = "";
        ack["DSP"][2]["DSP.5"]["DSP.5.1"] = "";
        ack["DSP"][2]["DSP.6"]["DSP.6.1"] = "";

    }
    else if(msgType=="ORU")
    {
        ack["MSH"]["MSH.9"]["MSH.9.1"] = "ACK";

        var mid = 0;
        var obr = xml.elements("OBR");
        var $barcode = obr["OBR.2"]["OBR.2.1"].toString();
        var newresult = dbConn.executeCachedQuery("select mid from ci_batches where assen='"+$barcode+"'");
        if(newresult.next())
        {
            mid = newresult.getInt(1);
            //logger.info("ORU - barcode: "+$barcode+" mid: "+mid);

            var obx = xml.elements("OBX");
            if(obx)
            {
                var uriners = dbConn.executeCachedQuery("select u_ID,u_abreviation_mc6410 from ci_urine_validity where u_abreviation_mc6410 is not null");
                var all_urine = {};
                while(uriners.next())
                {
                    try{
                        var uid = uriners.getInt(1);
                        var u_abreviation = uriners.getString(2);
                        u_abreviation = u_abreviation.toUpperCase();
                        all_urine[u_abreviation] = uid;
                    }catch (e){
                        logger.error(e);
                    }
                }

                for(var i in  obx)
                {
                    var tname = obx[i]["OBX.3"]["OBX.3.1"].toString();
                    var vl = obx[i]["OBX.4"]["OBX.4.1"].toString();
                    tname = tname.toUpperCase();
                    var testid = all_urine[tname];
                    if(!testid){
                        logger.error("ORU - Test  "+tname+ " not exists");
                        continue;
                    }

                    var result = dbConn.executeUpdate("INSERT INTO ci_result_test_speciments (mid,testid,conc,api_type) \
									values ('"+mid+"','"+testid+"','"+ vl +"',2) \
									ON DUPLICATE KEY UPDATE conc = '"+ vl +"', api_type = 2 ");
                    //logger.info("tname:"+tname+" => "+vl);
                }

                //log user
                dbConn.executeUpdate("INSERT INTO ci_log_users (user_id,user_action,mid,created) \
                values('-2','instrument_rstox','"+mid+"','"+$created+"')");

            }else{

                logger.error("ORU - barcode: "+$barcode+" mid: "+mid+ " . Error: No OBX");
            }
        }
        else{
            logger.error("ORU - barcode: "+$barcode+" not exists");
        }
    }
    else{
        ack = null;
        dbConn.close();
        throw('not support '+msgType );
    }



    dbConn.close();
} finally {
    if (dbConn) {
        dbConn.close();
    }
}

var hl7 = serializer.fromXML(ack);
var text = hl7.toString() +"";
var hl7new = "";
for(var i = 0;i<text.length;i++)
{
    hl7new += text[i] + "\0";
}



return hl7new;