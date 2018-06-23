#!/usr/bin/env bash
docker build --no-cache -t vfetc .
docker run -it --rm -v $(pwd)/data/tmp:/out vfetc files=data/vendor/agilent/batches.zip outputfile=/out/agilent_from_zip.txt && cat data/tmp/agilent_from_zip.txt
docker run -it --rm -v $(pwd)/data/tmp:/out vfetc files=data/vendor/agilent/example_batch1.txt,data/vendor/agilent/example_batch2.txt outputfile=/out/agilent.txt && cat data/tmp/agilent.txt
docker run -it --rm -v $(pwd)/data/tmp:/out vfetc files=data/vendor/sciex/example_batch1.txt outputfile=/out/sciex.txt && cat data/tmp/sciex.txt
docker run -it --rm -v $(pwd)/data/tmp:/out vfetc files=data/vendor/shimadzu/example_batch1.txt,data/vendor/shimadzu/example_batch2.txt,data/vendor/shimadzu/example_batch3.txt outputfile=/out/shimadzu.txt && cat data/tmp/shimadzu.txt
docker run -it --rm -v $(pwd)/data/tmp:/out vfetc files=data/vendor/waters/example_batch1.txt,data/vendor/waters/example_batch2.txt outputfile=/out/waters.txt && cat data/tmp/waters.txt