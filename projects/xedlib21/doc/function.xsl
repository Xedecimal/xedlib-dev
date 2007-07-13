<?xml version="1.0" encoding="ISO-8859-1"?>
<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">
<xsl:template match="/">
<html>
<head>
	<title> Function - <xsl:value-of select="root/@name" /> </title>
	<link href="../screen.css" type="text/css" rel="stylesheet" />
</head>
<body>

<p><b>
	<a href="index.xml">Index</a>
	:: <xsl:value-of select="root/doc/tag[@type='package']/@desc" />
</b></p>

<div class="blue">

<div class="head">
	<xsl:value-of select="root/doc/tag[@type='return']/@datatype" />
	<xsl:text> </xsl:text>
	<xsl:value-of select="root/@name" />
	(<xsl:for-each select="root/variable/@name">
		<xsl:value-of select="../../doc/tag[@name=current()]/@datatype" />
		<xsl:text> </xsl:text><b><xsl:value-of select="."/></b>
		<xsl:if test="position() != last()"><xsl:text>, </xsl:text></xsl:if>
	</xsl:for-each>)
</div>

<div class="body"><div class="item">

	<p><xsl:value-of select="root/doc/text()" /></p>
	<xsl:for-each select="root/doc/tag[@type='param']">
		<b><xsl:value-of select="@datatype"/></b>
		<xsl:text> </xsl:text><xsl:value-of select="@name"/>
		<xsl:text> </xsl:text><xsl:value-of select="."/><br/>
	</xsl:for-each>

	<xsl:for-each select="root/doc/tag[@type='example']">
		<pre class="yellow item">
			<xsl:value-of select="text()" disable-output-escaping="yes" />
		</pre>
	</xsl:for-each>

</div></div>

</div>

</body>
</html>
</xsl:template>
</xsl:stylesheet>