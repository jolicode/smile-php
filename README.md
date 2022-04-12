# What is Smile-PHP ?

Smile-PHP is an attempt at using the [Smile
Protocol](https://github.com/FasterXML/smile-format-specification) in PHP. If
you just want a quick look at what Smile is doing you may read this article as
well :
https://medium.com/code-with-ayush/understanding-smile-a-data-format-based-on-json-29972a37d376

Smile-PHP is still under development and is currently not ready at all.

Be careful with the input file since IDEs may format your binary string on
saving, making it a non-valid smile file. You probably don't want to save this
file using Control + S and rather use some other tools like the `cat` command or
Vim.

# Inspirations

Smile-PHP is mainly based on https://github.com/jhosmer/PySmile. However, it
also relies on https://github.com/ngyewch/smile-js and
https://github.com/zencoder/go-smile for some parts, especially for the tests
files where we merely took them from the latter.

# Requirements

Smile-PHP uses the PHP mb_string extension and the PHP BCMath extension, so you
must make sure your server has them installed.
