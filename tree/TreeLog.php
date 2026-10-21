<?php

declare(strict_types=1); // stops errors instead of converting them and catches mistakes early o

require_once __DIR__ . '/UpdateTree.php'; // code can't identify what SystemRoot is without this file 

class TreeLog //starts the blueprint 
{
    public const MAGIC = 'ULOG'; // the 4 letters written at the start of every log file that labels the file as "your log file" 
    public const VERSION = 4; // format version 
    public const HEADER_SIZE = 8; // the header is ULOG which is 4 bytes + version which is 4 bytes, equal to 8 bytes; records start after that
    public const HASH_SIZE = 32; // size of the hash in bytes

    public const STRUCTURAL_SIZE = 8 + self::HASH_SIZE; // checks that section isnt broken

    public const SIGNATURE_SIZE = 64; // Ed25519 signature is always exactly 64 bytes, use that number to check authorization section is right size

    public const TYPE_CODES = [ //lookup list that turns class names into numbers
        'Update' => 1, 
        'Lineage' => 2, 
        'Node' => 3, 
        'Network' => 4,
        'SystemRoot' => 5,
    ];

    private string $path; // file location such as '...\tree\log_test.bin'
    private int $size = 0; // how many bytes are in the file currently; next record starts at this byte
    private string $lastHash; // the hash of the last record; used to check if the file was changed by something else
    private array $built = []; // a list of every update object, oldest first; $built[0] is the first update
    private array $indexOf = []; // a look up of "this object --> its position in $built"; catches someone adding the same update twice
    private array $offsets = []; // where each record starts in the file
    private array $hashes = []; // the hash of each record, in the same order as $built and $offsets
    private array $sections = []; // holds each record's three section sizes so test can print them

    private string $publicKey; //the key used to check signatures
    private ?string $secretKey; // the key used to make signatures

 
    public function __construct(string $path, string $publicKey, ?string $secretKey = null)
{
    if ($secretKey !== null && sodium_crypto_sign_publickey_from_secretkey($secretKey) !== $publicKey) { //sodum crypto function pulls out secret key so it can be compared with the public key given
        throw new RuntimeException("Secret key doesn't match public key");
    }
    $this->publicKey = $publicKey;
    $this->secretKey = $secretKey;
    $this->path = $path; // saves the path into the object
    $this->lastHash = str_repeat("\0", self::HASH_SIZE); // starts with 32 zero bytes, the hash of nothing

        $buffer = is_file($path) ? file_get_contents($path) : ''; // if the file exists, read the whole thing into $buffer, if not use empty text
        if ($buffer === false) {
            throw new RuntimeException("Could not read {$path}");  // gives back false if file exists but couldnt be read
        }

        if ($buffer === '') { // an empty buffer means brand new log 
            $header = self::MAGIC . pack('V', self::VERSION); // builds the header
            if (file_put_contents($path, $header) === false) { // writes the header as a new file
                throw new RuntimeException("Could not create {$path}"); // if writing fails, stop with an error message
            }
            $this->size = self::HEADER_SIZE; // the file is now 8 bytes long, so size becomes 8
            return;
        }

        $this->loadFromBuffer($buffer); // if the system reaches this, the file had data so rebuild the tree from it; pickup where it left off
        $this->size = strlen($buffer); // strlen counts bytes in the buffer
    }

    public function append(Update $update): void // takes one update (append) and doesnt give back anything (void)
    {
        if ($this->secretKey === null) {
            throw new RuntimeException('No secret key, this log is read-only');
        }

        if (isset($this->indexOf[spl_object_id($update)])) { // is ID number for this object in memory (indexOf)
            throw new RuntimeException('This update is already in the log'); // if the eobject is already in memory, stop so the update isnt written twice
        }

        if ($update->Previous !== $this->newest()) {
            throw new RuntimeException('An update can only extend the newest update in the log'); // if the update doesnt point back to the current newest update, stop
        }

        $class = get_class ($update);
        if (!isset(self::TYPE_CODES[$class])) { // if the class name is not in the list of known types, stop because the file wouldnt know how to store it
            throw new RuntimeException("No type code for {$class}");
        }

        $structural = pack('VV', self::TYPE_CODES[$class], $update->seq) . $this->lastHash;
        $semantic = $update->label;
        $signed = self::section($structural) . self::section($semantic);
        $authorization = sodium_crypto_sign_detached($signed, $this->secretKey);
        $body = $signed . self::section($authorization);

        
        $record = pack('V', strlen($body)) . $body; // builds the full record: body's length, packed into 4 bytes, placed in front of body

        if(file_put_contents($this->path, $record, FILE_APPEND | LOCK_EX) === false) {
            throw new RuntimeException("Could not append to {$this->path}"); // if writing failed, stop with an error
        }

        $this->remember($update, $this->size, hash('sha256', $record, true), [strlen($structural), strlen($semantic), strlen($authorization)]); //remembers the update in memory
        $this->size += strlen($record); // file grew so add the record's length to size
    }

    

    public function newest(): ?Update // if $built is empty, give back null; otherwise, give back the last item which is the newest update
    {
        return $this->built === [] ? null : $this->built[count($this->built) - 1]; 
    }

    public function newestHash(): string {
        return bin2hex($this->lastHash); // returns the last hash as a string of hex characters
    }
    public function count(): int // count how many updates in the log 
    {
        return count($this->built);
    }

    public function offsets(): array // list of where each record starts
    {
        return $this->offsets;
    }

    public function sections(): array
    {
        return $this->sections;

    }

    private function loadFromBuffer(string $buffer): void // rebuilds the tree from the file bytes
    {
        if (strlen($buffer) < self::HEADER_SIZE || substr($buffer, 0, 4) !== self::MAGIC) { // check if file is shorter than 8 bytes, if it is then can't hold a header
            throw new RuntimeException("Not a tree log: {$this->path}"); // if either is true, it is not one of the log files, so it must stop
        }
        $version = unpack('Vversion', substr($buffer, 4, 4))['version']; // reads the version
        if ($version !== self::VERSION) { // if the version isnt 3
            throw new RuntimeException("Unsupported version {$version}"); // stop, code only code understands version 3
        }

        $classFor = array_flip(self::TYPE_CODES); // flips the type list around so 5 => 'SystemRoot', number from file can be converted into class name
        $pos = self::HEADER_SIZE; // reading position, like a bookmark; starts at byte 8 after the header
        $total = strlen($buffer); // how many bytes the file has so we know when to stop 
 
        while ($pos < $total) { // keep looping as long as the bookmark hasnt reached the end of the file
            if ($pos + 4 > $total) { // each record starts with 4 byte length
                throw new RuntimeException("Log cut off at byte {$pos}"); // if fewer bytes than 4 are left then stop 
            }
            $length = unpack('Vlength', substr($buffer, $pos, 4))['length']; // reads the 4 byte length at the bookmark; says how many bytes the rest of record has 
            
            if ($length < 12 || $pos + 4 + $length > $total) { // a record needs at least the three 4 byte section size 
                throw new RuntimeException("Log cut off at byte {$pos}"); // stop with an error
            }
            $cursor  = $pos + 4; // a second, smaller bookmark that moves inside this one record
            $end = $pos + 4 + $length; // where this record ends
            $structural = $this->readSection($buffer, $cursor, $end); //readSection, each one reads a size, takes that many bytes, and moves $cursor forward
            $semantic = $this->readSection($buffer, $cursor, $end); 
            $authorization = $this->readSection($buffer, $cursor, $end);
            if ($cursor !== $end) { // cursor should land exactly at the end of the record after reading all three sections, if not the sizes dont add up
                throw new RuntimeException("Section sizes don't add up at byte {$pos}"); // print error message, stop
            }
            if (strlen($structural) !== self::STRUCTURAL_SIZE){ // structural section must be 40 bytes
                throw new RuntimeException("Bad structural section at byte {$pos}"); //cant hold type, seq, and hash
            }

            $rec = unpack('Vtype/Vseq', substr($structural, 0, 8)); // pull type and seq from the first 8 bytes of the structural section 
            $prevHash = substr($structural, 8, self::HASH_SIZE); // pull previous hash from the next 32 bytes

            $i = count($this->built); // the record's position, how many updates are already built
            if($prevHash !== $this->lastHash) { 
                throw new RuntimeException("Hash chain broken at record {$i}");// if the previous hash in the record does not match the last hash, then the file was changed by something else, so stop with an error
            }
            if (strlen($authorization) !== self::SIGNATURE_SIZE) { //check if authorization section is exactly 4 bytes
                throw new RuntimeException("Missing signature at record {$i}"); //stop 
            }
            $signed = self::section($structural) . self::section($semantic); // rebuilds the exact bytes that append() signed
            if (!sodium_crypto_sign_verify_detached($authorization, $signed, $this->publicKey)) { // the check; takes the signature, bytes, and public key
                throw new RuntimeException("Bad signature at record {$i}");
            }

            $class = $classFor[$rec['type']] ?? null; // turns the type number back into a class name 
            if ($class === null) { // unknown type number means the file is broken 
                throw new RuntimeException("Unknown type code {$rec['type']} at byte {$pos}"); // stop 
            }

            $record = substr($buffer, $pos, 4 + $length); // grabs the whole record, including the 4 byte length at the start
            $update = new $class($this->newest(), $semantic, $rec['seq']); // builds the object from the record; points back to the current newest update, uses the label and sequence number from the record
            $this->remember($update, $pos, hash('sha256', $record, true), [strlen($structural), strlen($semantic), strlen($authorization)]); // remembers the update in memory and saves the hash of the record
            $pos = $end; // jump the main bookmark to the end of this record
        }
    }

    private function remember(Update $update, int $offset, string $hash, array $sizes): void // ...your comment...
{
    $this->indexOf[spl_object_id($update)] = count($this->built); // records the objects position 
    $this->built[] = $update;  // adds the object to the end of $built
    $this->offsets[] = $offset; // adds the record's starting byte to $offsets
    $this->hashes[] = $hash; // adds the hash of the record to $hashes
    $this->lastHash = $hash; // updates the last hash to the new record's hash
    $this->sections[] = $sizes;
}

private static function section(string $bytes): string 
{
    return pack('V', strlen($bytes)) . $bytes;
}

private function readSection(string $buffer, int &$cursor, int $end): string
{
    if ($cursor + 4 > $end) {
        throw new RuntimeException("Section cut off at byte {$cursor}");
    }
    $size = unpack('Vsize', substr($buffer, $cursor, 4))['size'];
    if ($cursor + 4 + $size > $end) {
        throw new RuntimeException("Section cut off at byte {$cursor}");
    }
    $bytes = substr($buffer, $cursor + 4, $size);
    $cursor += 4 + $size;
    return $bytes;
}
}