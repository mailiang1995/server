<?php
/**
 * Copyright 2007 Google Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */
/**
 * ProtocolMessage defines the base class for all protocol buffers.
 */
namespace google\net;

if (!defined('GOOGLE_APPENGINE_CLASSLOADER')) {
  require_once 'google/appengine/runtime/proto/Decoder.php';
  require_once 'google/appengine/runtime/proto/Encoder.php';
  require_once 'google/appengine/runtime/proto/ProtocolBufferDecodeError.php';
  require_once 'google/appengine/runtime/proto/ProtocolBufferEncodeError.php';
}
/**
 * The parent class of all protocol buffers.
 * Subclasses are automatically generated by php protocol buffer compiler.
 * Encoding methods can raise ProtocolBufferEncodeError if a value for an
 * integer or long field is too large, or if any required field is not set.
 * Decoding methods can raise ProtocolBufferDecodeError if they couldn't
 * decode correctly, or the decoded message doesn't have all required fields.
 */
abstract class ProtocolMessage {
  /**
   * Serializes the message and return it as a string.
   *
   * @throws ProtocolBufferEncodeError If the protocol buffer is not
   * initialized.
   *
   * @return string The serialized protocol buffer.
   */
  public function serializeToString() {
    $uninitialized = $this->checkInitialized();
    if ($uninitialized !== null) {
      throw new ProtocolBufferEncodeError(
        "Not initialized: " . $uninitialized);
    }
    return $this->serializePartialToString();
  }

  /**
   * Serializes a protocol buffer that might not have all of the required fields
   * set.
   *
   * @return string The serialized protocol buffer.
   */
  public function serializePartialToString() {
    $enc = new Encoder();
    $this->outputPartial($enc);
    $res = $enc->toString();

    # Continue to be a little paranoid about the correctness of the protocol
    # buffer implementation.
    if (mt_rand(0, 99) === 0 && $this->byteSizePartial() !== strlen($res)) {
      throw new ProtocolBufferEncodeError(
        "Internal bug: Encoded size doesn't match predicted");
    }
    return $res;
  }

  /**
   * Fills the message with a protocol buffer parsed from the given input
   * string.
   *
   * @param string $s The string containing a serialized protocol buffer.
   *
   * @throws ProtocolBufferDecodeError If the result message is not correctly
   * initialized.
   */
  public function parseFromString($s) {
    // Reads data from the string 's'.
    // Raises a ProtocolBufferDecodeError if, after successfully reading
    // in the contents of 's', this protocol message is still not initialized.
    $this->clear();
    $this->mergeFromString($s);
  }

  /**
   * Fills the message with a protocol buffer parsed from the given input
   * string. Will not fail if the resulting protocol buffer is not fully
   * initialized.
   *
   * @param string $s The string containing a serialized protocol buffer.
   */
  public function parsePartialFromString($s) {
    // Reads data from the string 's'.
    // Does not enforce required fields are set.
    $this->clear();
    $this->mergePartialFromString($s);
  }

  /**
   * Like parseFromString, fills the message with a protocol buffer parsed from
   * the given input string.
   *
   * @param string $s The string containing a serialized protocol buffer.
   *
   * @throws ProtocolBufferDecodeError If the result message is not correctly
   * initialized.
   */
  public function mergeFromString($s) {
    // Adds in data from the string 's'.
    // Raises a ProtocolBufferDecodeError if, after successfully merging
    // in the contents of 's', this protocol message is still not initialized.
    $this->mergePartialFromString($s);
    $uninitialized = $this->checkInitialized();
    if ($uninitialized !== null) {
      throw new ProtocolBufferDecodeError(
        "Not initialized: " . $uninitialized);
    }
  }

  /**
   * Like parsePartialFromString, fills the message with a protocol buffer
   * parsed from the given input string. Will not fail if the resulting protocol
   * buffer is not fully initialized.
   *
   * @param string $s The string containing a serialized protocol buffer.
   */

  public function mergePartialFromString($s) {
    // Merges in data from the string 's'.
    // Does not enforce required fields are set.
    $d = new Decoder($s, 0, strlen($s));
    $this->tryMerge($d);
  }

  /**
   * Copies data from another protocol buffer into this protocol buffer.
   *
   * @param mixed $pb The protocol buffer to copy from.
   */
  public function copyFrom($pb) {
    // copy data from another protocol buffer
    if ($pb === $this) {
      return;
    }
    $this->clear();
    $this->mergeFrom($pb);
  }

  /**
   * Checks if this protocol message has all of the requried fields initialized.
   *
   * @return bool true if all fields are initialized, false otherwise.
   */
  public function isInitialized() {
    $uninitializedFields = $this->checkInitialized();
    return $uninitializedFields === null;
  }

  public abstract function checkInitialized();
  public abstract function tryMerge($decoder);
  public abstract function outputPartial($encoder);
  public abstract function byteSizePartial();
  public abstract function clear();
  public abstract function mergeFrom($proto);
  public abstract function equals($proto);
  public abstract function shortDebugString();

  /**
   * Format protocol buffer as a text string for debugging.
   */
  protected static function debugFormatString($value) {
    $res = "";
    if (!empty($value)) {
      foreach (str_split($value) as $c) {
        $res .= ProtocolMessage::escapeByte($c);
      }
    }
    return '"' . $res . '"';
  }

  protected static function debugFormatDouble($value) {
    $val = "" . $value;
    if (strpos($val, "E") !== false) {
      return str_replace("E", "e", $val);
    }
    if (strpos($val, ".") === false) {
      $val .= ".0";
    }
    return $val;
  }

  protected static function lengthVarUint64($value) {
    if ($value < 0) {
      throw new ProtocolBufferEncodeError("Negative value");
    }
    if ($value <= 0x7f) {
      return 1;
    } elseif ($value <= 0x3fff) {
      return 2;
    } elseif ($value <= 0x1fffff) {
      return 3;
    } elseif ($value <= 0xfffffff) {
      return 4;
    } elseif (bccomp($value, "34359738367") <= 0) {
      # 0x7ffffffff
      return 5;
    } elseif (bccomp($value, "4398046511103") <= 0) {
      # 0x3ffffffffff
      return 6;
    } elseif (bccomp($value, "562949953421311") <= 0) {
      # 0x1ffffffffffff
      return 7;
    } elseif (bccomp($value, "72057594037927935") <= 0) {
      # 0xffffffffffffff
      return 8;
    } elseif (bccomp($value, "9223372036854775807") <= 0) {
      # 0x7fffffffffffffff
      return 9;
    } elseif (bccomp($value, "18446744073709551615") > 0) {
      # MAXUINT64
      throw new ProtocolBufferEncodeError("Value out of range: " . $value);
    } else {
      return 10;
    }
  }

  protected static function lengthVarInt64($value) {
    if ($value < 0) {
      if (bccomp($value, bcsub(0, bcpow(2,63))) < 0) {
        throw new ProtocolBufferEncodeError(
          "Value out of sint64 range: " . $value);
      }
      $value = bcadd($value, bcpow(2, 64));
    } elseif ($value > 2147483648 && bccomp($value, bcpow(2, 64)) >= 0) {
      throw new ProtocolBufferEncodeError(
        "Value out of sint64 range: " . $value);
    }
    return ProtocolMessage::lengthVarUint64($value);
  }

  protected static function lengthVarInt32($value) {
    return ProtocolMessage::lengthVarInt64($value);
  }

  protected static function lengthString($len) {
    return ProtocolMessage::lengthVarInt32($len) + $len;
  }

  protected static function debugFormatBool($b) {
    if ($b === true) {
      return "true";
    } else if ($b === false) {
      return "false";
    } else {
      return "???";
    }
  }

  protected static function debugFormatFloat($value) {
    return sprintf("%ff", $value);
  }

  protected static function debugFormatFixed32($value) {
    if ($value < 0) $value = bcadd($value, bcpow(2, 32));
    return ProtocolMessage::debugFormatFixed64($value);
  }

  protected static function debugFormatFixed64($value) {
    if ($value < 0) $value = bcadd($value, bcpow(2, 64));
    $res = "";
    do {
      $low = bcmod($value, 65536);
      $value = bcdiv($value, 65536);
      if ($value == 0) {
        if ($low != 0) {
          $res = sprintf("%x", $low) . $res;
        }
      } else {
        $res = sprintf("%04x", $low) . $res;
      }
    } while ($value != 0);

    if ("z" . $res === "z") {
      $res = "0";
    }

    return sprintf("0x%s", $res);
  }

  protected static function debugFormatInt32($value) {
    if ($value <= -2000000000 or $value >= 2000000000)
      return ProtocolMessage::debugFormatFixed32($value);
    return sprintf("%d", $value);
  }

  protected static function debugFormatInt64($value) {
    if (bccomp($value, "-20000000000000") <= 0
      or bccomp($value, "20000000000000")>= 0)
      return ProtocolMessage::debugFormatFixed64($value);
    return strval($value);
  }

  protected function checkProtoArray($arr) {
    if (sizeof($arr) == 0) {
      return;
    }
    // Quick approximate check that indexes of array are sane.
    $keys = array_keys($arr);
    if (end($keys) + 1 != sizeof($arr)) {
      throw new ProtocolBufferEncodeError("Proto array should not have holes");
    }
  }

  /**
   * Checks if two integers are equal.
   *
   * @param int $a The first integer to compare.
   * @param int $b The second integer to compare.
   *
   * @return bool True if the intergers are equal, false otherwise.
   */
  protected static function integerEquals($a, $b) {
    return ($a === $b) || (strval($a) === strval($b));
  }

  private static function escapeByte($c) {
    # Copied from python implementation:
    # For now we only escape the bare minimum to insure interoperabilty
    # and redability. In the future we may want to mimick the c++ behavior
    # more closely, but this will make the code a lot more messy.
    if ($c == "\n") return "\\n";  # optional escape
    if ($c == "\r") return "\\r";  # optional escape
    if ($c == "'") return "\\'";  # optional escape

    if ($c == "\"") return "\\\"";  # necessary escape
    if ($c == "\\") return "\\\\";  # necessary escape

    if ($c < "\x20" || $c >= "\x7F") {
      $o = unpack("C*", $c);
      return sprintf("\\%03o", $o[1]);
    }
    return $c;
  }
}
