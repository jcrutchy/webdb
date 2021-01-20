#!/bin/bash
grepcidr $1 <(echo $2)
exit 0
