# ms-vfetc
mass spec vendor feature export tool converter

# How to use
------

# Examples (php -f)
php -f src/vfetc.php files=data/vendor/agilent/example_batch1.txt,data/vendor/agilent/example_batch2.txt outputfile=data/tmp/agilent.txt
php -f src/vfetc.php files=data/vendor/sciex/example_batch1.txt outputfile=data/tmp/sciex.txt
php -f src/vfetc.php files=data/vendor/shimadzu/example_batch1.txt,data/vendor/shimadzu/example_batch2.txt,data/vendor/shimadzu/example_batch3.txt outputfile=data/tmp/shimadzu.txt
php -f src/vfetc.php files=data/vendor/waters/example_batch1.txt,data/vendor/waters/example_batch2.txt outputfile=data/tmp/waters.txt

# Examples (Docker)

docker build -t vfetc .

docker run -it --rm -v /home/vfetc/ms-vfetc/data/tmp:/out --name vfetc-running vfetc files=data/vendor/agilent/example_batch1.txt,data/vendor/agilent/example_batch2.txt outputfile=/out/agilent.txt
docker run -it --rm -v /home/vfetc/ms-vfetc/data/tmp:/out --name vfetc-running vfetc files=data/vendor/sciex/example_batch1.txt outputfile=/out/sciex.txt
docker run -it --rm -v /home/vfetc/ms-vfetc/data/tmp:/out --name vfetc-running vfetc files=data/vendor/shimadzu/example_batch1.txt,data/vendor/shimadzu/example_batch2.txt,data/vendor/shimadzu/example_batch3.txt outputfile=/out/shimadzu.txt
docker run -it --rm -v /home/vfetc/ms-vfetc/data/tmp:/out --name vfetc-running vfetc files=data/vendor/waters/example_batch1.txt,data/vendor/waters/example_batch2.txt outputfile=/out/waters.txt