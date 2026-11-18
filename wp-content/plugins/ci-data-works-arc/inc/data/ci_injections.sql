/* 
 --phpMiniAdmin dump 1.9.110429
-- Datetime: 2016-02-01 17:19:00
-- Host: db
-- Database: default_wp

 */
DROP TABLE IF EXISTS `ci_injections`;

CREATE TABLE `ci_injections` (  `inj_ID` int(11) NOT NULL AUTO_INCREMENT,  `test` varchar(34) DEFAULT NULL,  `panel_U` varchar(7) DEFAULT NULL,  `panel_O` varchar(7) DEFAULT NULL,  PRIMARY KEY (`inj_ID`)) ENGINE=InnoDB AUTO_INCREMENT=84 DEFAULT CHARSET=utf8;

INSERT INTO `ci_injections` VALUES ('2','BUPRENORPHINE','PP','PP');
INSERT INTO `ci_injections` VALUES ('3','NORBUPRENORPHINE','PP','PP');
INSERT INTO `ci_injections` VALUES ('4','CODEINE','PP','PP');
INSERT INTO `ci_injections` VALUES ('5','HYDROCODONE','PP','PP');
INSERT INTO `ci_injections` VALUES ('6','HYDROMORPHONE','PP','PP');
INSERT INTO `ci_injections` VALUES ('7','MEPERIDINE','PP','PP');
INSERT INTO `ci_injections` VALUES ('8','MORPHINE','PP','PP');
INSERT INTO `ci_injections` VALUES ('9','OXYCODONE','PP','PP');
INSERT INTO `ci_injections` VALUES ('10','OXYMORPHONE','PP','PP');
INSERT INTO `ci_injections` VALUES ('11','TAPENTADOL ','PP','PP');
INSERT INTO `ci_injections` VALUES ('12','EDDP','PP','PP');
INSERT INTO `ci_injections` VALUES ('13','METHADONE','PP','PP');
INSERT INTO `ci_injections` VALUES ('14','FENTANYL','PP','PP');
INSERT INTO `ci_injections` VALUES ('15','NORFENTANYL','PP','');
INSERT INTO `ci_injections` VALUES ('16','TRAMADOL','PP','PP');
INSERT INTO `ci_injections` VALUES ('17','ALPRAZOLAM','PP','PP');
INSERT INTO `ci_injections` VALUES ('18','HYDROXYALPRAZOLAM','PP','PP');
INSERT INTO `ci_injections` VALUES ('19','7 AMINO CLONAZAPAM -CLONAZEPAM','PP','PP');
INSERT INTO `ci_injections` VALUES ('20','DIAZEPAM','','PP');
INSERT INTO `ci_injections` VALUES ('21','NORDIAZEPAM','PP','PP');
INSERT INTO `ci_injections` VALUES ('22','OXAZEPAM','PP','PP');
INSERT INTO `ci_injections` VALUES ('23','TEMAZEPAM','PP','PP');
INSERT INTO `ci_injections` VALUES ('24','LORAZEPAM','PP','PP');
INSERT INTO `ci_injections` VALUES ('25','FLURAZEPAM','PP','PP');
INSERT INTO `ci_injections` VALUES ('26','FLUNITRAZEPAM','PP','PP');
INSERT INTO `ci_injections` VALUES ('27','AMPHETAMINE','PP','PP');
INSERT INTO `ci_injections` VALUES ('28','METHYLPHENIDATE ','PP','PP');
INSERT INTO `ci_injections` VALUES ('29','6-MAM (HEROIN)','PP','PP');
INSERT INTO `ci_injections` VALUES ('30','BENZOYLECGONINE-COCAINE METABOLITE','PP','PP');
INSERT INTO `ci_injections` VALUES ('31','MDA','PP','PP');
INSERT INTO `ci_injections` VALUES ('32','MDEA','PP','PP');
INSERT INTO `ci_injections` VALUES ('33','MDMA','PP','PP');
INSERT INTO `ci_injections` VALUES ('34','METHAMPHETAMINE','PP','PP');
INSERT INTO `ci_injections` VALUES ('35','PCP','PP','PP');
INSERT INTO `ci_injections` VALUES ('36','CARISOPRODOL','PP','PP');
INSERT INTO `ci_injections` VALUES ('37','MEPROBAMATE','PP','PP');
INSERT INTO `ci_injections` VALUES ('38','GABAPENTIN','PP','PP');
INSERT INTO `ci_injections` VALUES ('39','PREGABALIN','PP','PP');
INSERT INTO `ci_injections` VALUES ('40','THC','','PP');
INSERT INTO `ci_injections` VALUES ('41','THC-COOH','THC','THC');
INSERT INTO `ci_injections` VALUES ('42','PHENOBARBITAL','THC','THC');
INSERT INTO `ci_injections` VALUES ('43','SECOBARBITAL','THC','THC');
INSERT INTO `ci_injections` VALUES ('44','AMOBARBITAL PENTOBARBITAL','THC','THC');
INSERT INTO `ci_injections` VALUES ('45','BUTALBITAL','THC','THC');
INSERT INTO `ci_injections` VALUES ('46','BUTABARBITAL','THC','THC');
INSERT INTO `ci_injections` VALUES ('47','KETAMINE','TBS','');
INSERT INTO `ci_injections` VALUES ('48','Zolpidem','TBS','');
INSERT INTO `ci_injections` VALUES ('49','Triazolam','TBS','');
INSERT INTO `ci_injections` VALUES ('50','Zaleplon','TBS','');
INSERT INTO `ci_injections` VALUES ('51','zopiclone','TBS','');
INSERT INTO `ci_injections` VALUES ('52','Cotinine','TBS','');
INSERT INTO `ci_injections` VALUES ('53','IMIPRAMINE','TBS','');
INSERT INTO `ci_injections` VALUES ('54','DESPRAMINE','TBS','');
INSERT INTO `ci_injections` VALUES ('55','NORTRIPTYLINE','TBS','');
INSERT INTO `ci_injections` VALUES ('56','AMITRIPTYLINE','TBS','');
INSERT INTO `ci_injections` VALUES ('57','METHYLENEDIOXYPYROVALERONE','TBS','');
INSERT INTO `ci_injections` VALUES ('58','METHYLONE','TBS','');
INSERT INTO `ci_injections` VALUES ('59','MEPHEDRONE','TBS','');
INSERT INTO `ci_injections` VALUES ('60','SPICE/K2/BLACK MAMA/FAKE','TBS','');
INSERT INTO `ci_injections` VALUES ('61','WEED/BOMBAY BLUE/BLISS','TBS','');
INSERT INTO `ci_injections` VALUES ('62','AM-2201 4-OH-PENTYL','TBS','');
INSERT INTO `ci_injections` VALUES ('63','JWH-018 4-OH-PENTYL','TBS','');
INSERT INTO `ci_injections` VALUES ('64','JWH-018 5-OH-PENTYL','TBS','');
INSERT INTO `ci_injections` VALUES ('65','JWH-018 PENTANOIC ACID','TBS','');
INSERT INTO `ci_injections` VALUES ('66','JWH-073 3-OH-BUTYL','TBS','');
INSERT INTO `ci_injections` VALUES ('67','JWH-073 4-OH-BUTYL','TBS','');
INSERT INTO `ci_injections` VALUES ('68','JWH-073 BUTANOIC ACID','TBS','');
INSERT INTO `ci_injections` VALUES ('69','JWH-081 5-OH-PENTYL','TBS','');
INSERT INTO `ci_injections` VALUES ('70','JWH-200 5-OH-INDOLE','TBS','');
INSERT INTO `ci_injections` VALUES ('71','JWH-200 6-OH-INDOLE','TBS','');
INSERT INTO `ci_injections` VALUES ('72','JWH-250 5-CARBOXYPENTYL','TBS','');
INSERT INTO `ci_injections` VALUES ('73','JWH-250 5-OH-INDOLE','TBS','');
INSERT INTO `ci_injections` VALUES ('74','JWH-250 5-OH-PENTYL','TBS','');
INSERT INTO `ci_injections` VALUES ('75','RCS-4 5-CARBOXYPENTYL','TBS','');
INSERT INTO `ci_injections` VALUES ('76','RCS-4 5-OH-PENTYL','TBS','');
INSERT INTO `ci_injections` VALUES ('77','Norcodeine','TBS','');
INSERT INTO `ci_injections` VALUES ('78','Norhydrocodone','TBS','');
INSERT INTO `ci_injections` VALUES ('79','Noroxymorphone','TBS','');
INSERT INTO `ci_injections` VALUES ('80','Noroxycodone','TBS','');
INSERT INTO `ci_injections` VALUES ('81','Dihydrocodeine','TBS','');
INSERT INTO `ci_injections` VALUES ('82','Ethanol*','Alcohol',NULL);
INSERT INTO `ci_injections` VALUES ('83','Methamphetamine d/l Isomers','DL',NULL);
