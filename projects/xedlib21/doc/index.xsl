<?xml version="1.0" encoding="ISO-8859-1"?>
<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">
<xsl:template match="/">
<html>
<head>
	<title> Documentation Root </title>
	<link href="../screen.css" type="text/css" rel="stylesheet" />
</head>
<body>

<table><tr><td valign="top">
<xsl:for-each select="root/package">
	<a>
		<xsl:attribute name="href">
			#<xsl:value-of select="@name"/>
		</xsl:attribute>
		<xsl:value-of select="@name" />
	</a><br/>
</xsl:for-each>

</td><td>

<xsl:for-each select="root/package">
	<div class="package">
		<xsl:attribute name="id">
			<xsl:value-of select="@name"/>
		</xsl:attribute>
		<xsl:value-of select="@name"/>
	</div>

	<xsl:if test="./class">
	<div class="yellow">
	<p class="head"><b>Classes</b></p>
	<p class="yellow body item">
	<xsl:for-each select="./class">
		<b><xsl:value-of select="doc/tag[@type='access']"/></b><xsl:text> </xsl:text>
		<a>
			<xsl:attribute name="href">
				class_<xsl:value-of select="@name"/>.xml
			</xsl:attribute>
			<xsl:value-of select="@name" />
		</a>
		<b><xsl:value-of select="@package" /></b>
		<xsl:if test="@extends">
			<xsl:text> </xsl:text><b>extends</b><xsl:text> </xsl:text>
			<a>
				<xsl:attribute name="href">
					class_<xsl:value-of select="@extends" />.xml
				</xsl:attribute>
				<xsl:value-of select="@extends" />
			</a>
		</xsl:if><br />
	</xsl:for-each>
	</p>
	</div>
	</xsl:if>

	<xsl:if test="./function">
	<div class="blue">
	<p class="blue head"><b>Functions</b></p>
	<p class="blue body item">
	<xsl:for-each select="./function">
		<a>
			<xsl:attribute name="href">
				function_<xsl:value-of select="@name"/>.xml
			</xsl:attribute>
			<xsl:value-of select="@name" />
		</a><br />
		<xsl:value-of select="@extends" />
	</xsl:for-each>
	</p>
	</div>
	</xsl:if>

	<xsl:if test="./variable">
	<div class="green">
	<p class="green head">Variables</p>
	<p class="green body item">
	<table>
	<tr><th>Name</th><th>Filename</th><th>Line</th></tr>
	<xsl:for-each select="./variable">
		<a>
			<xsl:attribute name="href">
				variable_<xsl:value-of select="@name"/>.xml
			</xsl:attribute>
			<xsl:value-of select="@name" /><br />
		</a>
	</xsl:for-each>
	</table>
	</p>
	</div>
	</xsl:if>

	<xsl:if test="./define">
	<div class="red">
	<p class="red head">Defines</p>
	<p class="red body item">
	<table>
	<tr><th>Name</th><th>Value</th><th>Filename</th><th>Line</th></tr>
	<xsl:for-each select="./define">
		<tr>
			<td><xsl:value-of select="@name" /></td>
			<td><xsl:value-of select="@value" /></td>
			<td><xsl:value-of select="@file" /></td>
			<td><xsl:value-of select="@line" /></td>
		</tr>
	</xsl:for-each>
	</table>
	</p>
	</div>
	</xsl:if>
</xsl:for-each>

</td></tr></table>

</body>
</html>
</xsl:template>
</xsl:stylesheet>