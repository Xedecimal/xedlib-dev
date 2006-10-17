<?xml version="1.0" encoding="ISO-8859-1"?>
<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">
<xsl:template match="/">
<html>
<head>
	<title> Documentation! </title>
	<link href="../screen.css" type="text/css" rel="stylesheet" />
</head>
<body>
<p><b>Classes</b></p>
<p>
<xsl:for-each select="root/class">
	<a>
		<xsl:attribute name="href">
			class_<xsl:value-of select="@name"/>.xml
		</xsl:attribute>
		<xsl:value-of select="@name" /><br />
	</a>
</xsl:for-each>
</p>
<p><b>Functions</b></p>
<p>
<xsl:for-each select="root/function">
	<a>
		<xsl:attribute name="href">
			function_<xsl:value-of select="@name"/>.xml
		</xsl:attribute>
		<xsl:value-of select="@name" />
	</a><br />
</xsl:for-each>
</p>
<p><b>Variables</b></p>
<p>Rule of thumb, have no existing global
variables!</p>
<p>
<xsl:for-each select="root/variable">
	<a>
		<xsl:attribute name="href">
			variable_<xsl:value-of select="@name"/>.xml
		</xsl:attribute>
		<xsl:value-of select="@name" /><br />
	</a>
</xsl:for-each>
</p>
</body>
</html>
</xsl:template>
</xsl:stylesheet>