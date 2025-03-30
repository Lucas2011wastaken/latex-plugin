# latex-plugin

A plugin for Wordpress that support LaTeX syntnex(inline only cuz I'm lazy) by calling [latex2svgAPI](https://github.com/Lucas2011wastaken/latex2svgAPI).

Perfect(i guess) plugin that can handle all the situations of latex syntex. After configuration, it will zoom and align the code automatically.

# how to use

1. Download the code and upoad it to your Wordpress server in `https://example.com/wp-admin/plugin-install.php`.
2. Configure the plugin via `Settings - LaTeX to SVG Settings`.

|Value|Description|
|:--:|:---|
|`API 密钥`|Your API token|
|`API 地址`|url of the API|
|`缩放比例`|Zoom rate(don't change it if the default 1.2 is fine)|
|`渲染后 LaTeX 白边大小`|`border` parameters for standalone doc class(don't change it if the default 0.2 is fine)|
|`常用的 chemfig 键长，即“-[2,0.8]”中的那个0.8。用于估算svg的高度。`|your habitual bond length in chemfig syntex, a.k.a. `0.8` in `-[2,0.8]`. this is to estimate the height of the output.|

3. make sure the current server user have the permission to write in `wp-content/cache/latex-svg-cache/`.

4. enjoy!

To avoid conflicts with KaTeX, the plugin only resolve `$$abc$$` as inline.

For instance, `$$\LaTeX$$` will pass `\LaTeX` to the API, and `$$$\lim\limits_{x\rightarrow 0}f(x)$$$` will pass `$\lim\limits_{x\rightarrow 0}f(x)$` to the API.

Since the API is sensitive to `$$` use, plz do standardize the use of `$`. e.g. If you type `$$$\LaTeX$$$` the server will report an error like this: `公式错误：LaTeXCompileFault：! You can&#8217;t use '\spacefactor&#8217; in math mode.'`
