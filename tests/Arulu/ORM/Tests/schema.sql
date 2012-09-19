DROP TABLE IF EXISTS `stupidorm_apellidos`;
CREATE TABLE `stupidorm_apellidos` (`ID` int(11) NOT NULL AUTO_INCREMENT, `Apellido` varchar(20) NOT NULL, PRIMARY KEY (`ID`)) ENGINE=MyISAM DEFAULT CHARSET=utf8;
INSERT INTO `stupidorm_apellidos` (`Apellido`) VALUES('garcia'),('ruiz'),('sanchez'),('kennedy'),('hilario'),('castro'),('molina'),('martinez');
DROP TABLE IF EXISTS `stupidorm_nombres`;
CREATE TABLE `stupidorm_nombres` (`ID` int(11) NOT NULL AUTO_INCREMENT, `Nombre` varchar(20) NOT NULL, `stupidorm_apellidos_ID` int(11) NOT NULL, PRIMARY KEY (`ID`)) ENGINE=MyISAM DEFAULT CHARSET=utf8;
INSERT INTO `stupidorm_nombres` (`Nombre`, `stupidorm_apellidos_ID`) VALUES('pedro',1),('pablo',2),('juan',1),('toni',2);