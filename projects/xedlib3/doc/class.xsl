<?xml version="1.0" encoding="ISO-8859-1"?>
<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">
<xsl:template match="/">
<html>
<head>
	<title> Documentation! </title>
	<link href="../screen.css" type="text/css" rel="stylesheet" />
</head>
<body>

<div class="yellow">
<p class="yellow head"><a href="index.xml">Index</a> :: <xsl:value-of select="root/@name" /></p>
<p class="yellow body">
	<p class="yellow item"><xsl:value-of select="root/doc/text()" /></p>

	<xsl:for-each select="root/doc/tag[@type='example']">
		<pre class="yellow item">
		<xsl:value-of select="text()" disable-output-escaping="yes" />
		</pre>
	</xsl:for-each>
</p>
</div>

<xsl:if test="root/variable">
<div class="blue">
<p class="blue head">Properties</p>
<p class="blue body item">
<xsl:for-each select="root/variable">
	<p>
		<b><xsl:value-of select="@modifier"/></b><xsl:text> </xsl:text>
		<b><xsl:value-of select="./doc/tag[@type='var']" /></b><xsl:text> </xsl:text>
		<xsl:value-of select="@name" />
	</p>
	<p><xsl:value-of select="doc/text()" /></p>
</xsl:for-each>
</p>
</div>
</xsl:if>

<div class="green">
<p class="green head">Methods</p>
<p class="green body">
<xsl:for-each select="root/function">
	<div class="green item">
	<p>
		<xsl:value-of select="doc/tag[@type='access']"/><xsl:text> </xsl:text>
		<b><xsl:value-of select="doc/tag[@type='return']/@datatype"/></b><xsl:text> </xsl:text>
	<xsl:value-of select="@name" />(
		<xsl:for-each select="variable/@name">
			<b><xsl:value-of select="../../doc/tag[@name=current()]/@datatype" /></b><xsl:text> </xsl:text>
			<xsl:value-of select="."/><xsl:text> </xsl:text>
		</xsl:for-each>
	)</p>
	<p><xsl:value-of select="doc/text()" /></p>
	<table>
		<xsl:for-each select="doc/tag[@type='param']">
			<tr>
			<td><b><xsl:value-of select="@datatype"/></b></td>
			<td><xsl:value-of select="@name"/></td>
			<td><xsl:value-of select="."/></td>
			</tr>
		</xsl:for-each>
	</table>
	</div>
</xsl:for-each>
</p>
</div>

</body>
</html>
</xsl:template>
</xsl:stylesheet>