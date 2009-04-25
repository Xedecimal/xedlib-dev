<?xml version="1.0" encoding="ISO-8859-1"?>
<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">
<xsl:template match="/">
<html>
<head>
	<title> Documentation! </title>
	<link href="../screen.css" type="text/css" rel="stylesheet" />
</head>
<body>
<div class="blue">
<p class="blue head">Variable <xsl:value-of select="root/@name" /></p>
<p class="blue body item">
<xsl:value-of select="root/doc" />
</p>
</div>
</body>
</html>
</xsl:template>
</xsl:stylesheet>