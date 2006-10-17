<?xml version="1.0" encoding="ISO-8859-1"?>
<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">
<xsl:template match="/">
<html>
<head>
	<title> Documentation! </title>
	<link href="../screen.css" type="text/css" rel="stylesheet" />
</head>
<body>

<div class="head">
<p class="head_head"><xsl:value-of select="root/@name" /></p>
<p class="head_body"><xsl:value-of select="root/doc/text" /></p>
</div>

<div class="properties">
<p class="properties_head">Properties</p>
<p class="properties_body">
<xsl:for-each select="root/variable">
	<p>
		<b><xsl:value-of select="./doc/tag[@type='var']" /></b>
		<xsl:value-of select="@name" />
	</p>
	<p><xsl:value-of select="./doc/text" /></p>
</xsl:for-each>
</p>
</div>

<div class="methods">
<p class="methods_head"><b>Methods</b></p>
<p class="methods_body">
<xsl:for-each select="root/function">
	<p><xsl:value-of select="@name" />&#160;(
		<xsl:for-each select="variable">
			<b><xsl:value-of select="../doc/param[@name=@name]/@type" /></b>&#160;
			<xsl:value-of select="@name"/>&#160;
		</xsl:for-each>
	)</p>
	<p><xsl:value-of select="doc/text" /></p>
</xsl:for-each>
</p>
</div>

</body>
</html>
</xsl:template>
</xsl:stylesheet>