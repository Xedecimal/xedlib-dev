<?xml version="1.0" encoding="ISO-8859-1"?>
<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">
<xsl:template match="/">
<html>
<head>
	<title> Documentation! </title>
</head>
<body>
<p><b>Functions</b></p>
<p>
<xsl:for-each select="root/function">
	<a>
		<xsl:attribute name="href">
			<xsl:value-of select="name"/>.xml
		</xsl:attribute>
		<xsl:value-of select="name" />
	</a><br />
</xsl:for-each>
</p>
<p><b>Classes</b></p>
<p>
<xsl:for-each select="root/class">
	<a>
		<xsl:attribute name="href">
			<xsl:value-of select="name"/>.xml
		</xsl:attribute>
		<xsl:value-of select="name" /><br />
	</a>
</xsl:for-each>
</p>
</body>
</html>
</xsl:template>
</xsl:stylesheet>