<?php

function VSN1notempty($s) { return strlen(trim($s)) && $s[0] != '#'; }

function ParseVSN1Tokens($tokens, &$pos)
{
  $end = count($tokens);
  $res = Array();
  while($pos < $end)
  {
    if($tokens[$pos][0] == '"') return stripslashes(substr($tokens[$pos++], 1, -1));
    if(preg_match('/^[0-9]/', $tokens[$pos])) return (int)$tokens[$pos++];
    if($tokens[$pos] == '{') { ++$pos; continue; }
    if($tokens[$pos] == '}') { ++$pos; break; }
    if($tokens[$pos] == '[')
    {
      ++$pos;
      for(;;)
      {
        if($pos >= $end) break;
        if($tokens[$pos] == ']') { ++$pos; break; }
        $res[] = ParseVSN1Tokens($tokens, $pos);
        if($tokens[$pos] == ',') ++$pos;
      }
      return $res;
    }
    $key = $tokens[$pos++];
    if($tokens[$pos] == '=') ++$pos;
    $res[$key] = ParseVSN1Tokens($tokens, $pos);
  }
  return $res;
}

function parse_vsn1($s)
{
  $tokens = preg_split('@(#.*|"(?:\\\"|[^"])*"|[a-zA-Z_][-a-zA-Z_0-9]*|[0-9]+|.)@',
                       $s, -1, PREG_SPLIT_DELIM_CAPTURE);
  $tokens = array_values(array_filter($tokens, 'VSN1notempty'));
  $pos = 0;
  return ParseVSN1Tokens($tokens, $pos);
}

