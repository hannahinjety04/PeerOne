<?php 

declare(strict_types= 1);

class Update { 
    public ?Update $Previous;
    public string $label;
    public int $seq;
    public function __construct(?Update $Prevous, string $label = '', int $seq = 0) {
        $this->Previous = $Prevous;
        $this->label = $label;
        $this->seq = $seq;
    }
}

// Two main update types

class ExecutiveUpdate extends Update {}
class EndorsementUpdate extends Update{}

// New (Genesis Executive) types
class NewUpdate extends ExecutiveUpdate {}
class NewLineage extends ExecutiveUpdate {}
class NewNode extends ExecutiveUpdate {}
class NewNetwork extends ExecutiveUpdate {}
class newSystemRoot extends ExecutiveUpdate {}

// Genesis (Genesis Endorsement) types

class UpdateGenesis extends EndorsementUpdate {}
class LineageGensis extends EndorsementUpdate {}
class NodeGenesis extends EndorsementUpdate {}
class NetworkGenesis extends EndorsementUpdate {}
class SystemRootGenesis extends EndorsementUpdate {}

//Lineage hierarchy (immutable)

class Lineage extends Update {
    public function __construct(?Lineage $Previous, string $label = '', int $seq = 0) {
        parent::__construct($Previous, $label, $seq);
    }
}

class Node extends Lineage {
    public function __construct(?Node $Previous, string $label = '', int $seq = 0) {
        parent::__construct($Previous, $label, $seq);
}
}

class Network extends Node {
    public function __construct(?Network $Previous, string $label = '', int $seq = 0) {
        parent::__construct($Previous, $label, $seq);
    }
}

class SystemRoot extends Network {
    public function __construct(?SystemRoot $Previous, string $label = '', int $seq = 0) {
        parent::__construct($Previous, $label, $seq);
    }
}
