# ms-vfetc
Mass spectrometry vendor feature export tool converter. Compatible with Agilent, Sciex, Shimadzu and Waters exported data.

[![Codacy Badge](https://api.codacy.com/project/badge/Grade/10a7549f7e834de5adc61359748c9881)](https://www.codacy.com/app/michaelvanvliet/ms-vfetc?utm_source=github.com&amp;utm_medium=referral&amp;utm_content=leidenuniv-lacdr-abs/ms-vfetc&amp;utm_campaign=Badge_Grade)

# How to use
------

# Examples (php -f)
```
php -f src/vfetc.php files=data/vendor/agilent/example_batch1.txt,data/vendor/agilent/example_batch2.txt outputfile=data/tmp/agilent.txt
```
```
php -f src/vfetc.php files=data/vendor/sciex/example_batch1.txt outputfile=data/tmp/sciex.txt
```
```
php -f src/vfetc.php files=data/vendor/shimadzu/example_batch1.txt,data/vendor/shimadzu/example_batch2.txt,data/vendor/shimadzu/example_batch3.txt outputfile=data/tmp/shimadzu.txt
```
```
php -f src/vfetc.php files=data/vendor/waters/example_batch1.txt,data/vendor/waters/example_batch2.txt outputfile=data/tmp/waters.txt
```

# Examples (Docker)

First build the container to use it interactively.
```
docker build -t vfetc .
```

Then run ms-vfetc with the data folder mounted in the container to be able to pass and retrieve the data.
```
docker run -it --rm -v /home/vfetc/ms-vfetc/data/tmp:/out --name vfetc-running vfetc files=data/vendor/agilent/example_batch1.txt,data/vendor/agilent/example_batch2.txt outputfile=/out/agilent.txt
```
```
docker run -it --rm -v /home/vfetc/ms-vfetc/data/tmp:/out --name vfetc-running vfetc files=data/vendor/sciex/example_batch1.txt outputfile=/out/sciex.txt
```
```
docker run -it --rm -v /home/vfetc/ms-vfetc/data/tmp:/out --name vfetc-running vfetc files=data/vendor/shimadzu/example_batch1.txt,data/vendor/shimadzu/example_batch2.txt,data/vendor/shimadzu/example_batch3.txt outputfile=/out/shimadzu.txt
```
```
docker run -it --rm -v /home/vfetc/ms-vfetc/data/tmp:/out --name vfetc-running vfetc files=data/vendor/waters/example_batch1.txt,data/vendor/waters/example_batch2.txt outputfile=/out/waters.txt
```
