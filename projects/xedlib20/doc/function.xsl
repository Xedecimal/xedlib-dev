<?xml version="1.0" encoding="ISO-8859-1"?>
<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">
<xsl:template match="/">
<html>
<head>
	<title> Function - <xsl:value-of select="root/@name" /> </title>
	<link href="../screen.css" type="text/css" rel="stylesheet" />
</head>
<body>
<p class="green head">
<a href="index.xml">Index</a> :: 
<xsl:value-of select="doc/tag[@type='access']"/><xsl:text> </xsl:text>
	<b><xsl:value-of select="doc/tag[@type='return']"/></b><xsl:text> </xsl:text>
<xsl:value-of select="root/@name" />(
	<xsl:for-each select="root/variable/@name">
		<b><xsl:value-of select="../../doc/tag[@name=current()]/@datatype" /></b><xsl:text> </xsl:text>
		<xsl:value-of select="."/><xsl:text> </xsl:text>
	</xsl:for-each>
)
</p>
<p class="green body item">
<p><xsl:value-of select="root/doc/text()" /></p>
<p>
	<xsl:for-each select="root/doc/tag[@type='param']">
		<b><xsl:value-of select="@datatype"/></b>&#160;
		<xsl:value-of select="@name"/>&#160;
		<xsl:value-of select="."/><br/>
	</xsl:for-each>
</p>
</p>
</body>
</html>
</xsl:template>
</xsl:stylesheet>