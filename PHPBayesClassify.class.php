<?php

require_once( 'pdo-wrapper/class.db.php' );

class bayesClassify {

	//private database access variables
	private $dbName     = DB_NAME;
	private $dbHost     = DB_HOST;
	private $dbUser     = DB_USER;                              
	private $dbPass     = DB_PASSWORD;
	//information about the database schema
	private $wordMappingTableName = 'seufFetch_wordMapping'; //the table that will hold words mapped to categories
	private $objectTableName = 'testData'; //the table that contains the objects that are / will be classified
	private $daysBack = 7; //the number of days back over which to fetch objects
	private $objectDateColumnName = 'dateCreated'; //the name of the date column that will be queried against for daysBack in objectTableName
	private $objectTitleColumnName = 'title'; //the title column for the object. This is what the classifier classifies against
	private $objectClassColumnName = 'category'; //the category column for the object. This holds an id or string and represents the classification of the object
	//public variables
	public $stopwords;
	public $objects;
	public $classifications;
	public $words;

	public function __construct( ) {
		//stop words
		$this->stopwords = array("a", "about", "above", "above", "across", "after", "afterwards", "again", "against", "all", "almost", "alone", 
			"along", "already", "also","although","always","am","among", "amongst", "amoungst", "amount", "an", "and", "another", "any","anyhow",
			"anyone","anything","anyway", "anywhere", "are", "around", "as", "at", "back","be","became", "because","become","becomes", "becoming", 
			"been", "before", "beforehand", "behind", "being", "below", "beside", "besides", "between", "beyond", "bill", "both", "bottom","but", "by", 
			"call", "can", "cannot", "cant", "co", "con", "could", "couldnt", "cry", "de", "describe", "detail", "do", "done", "down", "due", "during", 
			"each", "eg", "eight", "either", "eleven","else", "elsewhere", "empty", "enough", "etc", "even", "ever", "every", "everyone", "everything", 
			"everywhere", "except", "few", "fifteen", "fify", "fill", "find", "fire", "first", "five", "for", "former", "formerly", "forty", "found", 
			"four", "from", "front", "full", "further", "get", "give", "go", "goes", "had", "has", "hasnt", "have", "he", "hence", "her", "here", "hereafter", 
			"hereby", "herein", "hereupon", "hers", "herself", "him", "himself", "his", "how", "however", "hundred", "i", "ie", "if", "in", "inc", "indeed", 
			"interest", "into", "is", "it", "its", "itself", "keep", "last", "latter", "latterly", "least", "less", "ltd", "made", "many", "may", "me", 
			"meanwhile", "might", "mill", "mine", "more", "moreover", "most", "mostly", "move", "much", "must", "my", "myself", "name", "namely", "neither", 
			"never", "nevertheless", "next", "nine", "no", "nobody", "none", "noone", "nor", "not", "nothing", "now", "nowhere", "of", "off", "often", "on", 
			"once", "one", "only", "onto", "or", "other", "others", "otherwise", "our", "ours", "ourselves", "out", "over", "own","part", "per", "perhaps", 
			"please", "put", "rather", "re", "same", "see", "seem", "seemed", "seeming", "seems", "serious", "several", "she", "should", "show", "side", 
			"since", "sincere", "six", "sixty", "so", "some", "somehow", "someone", "something", "sometime", "sometimes", "somewhere", "still", "such", 
			"system", "take", "ten", "than", "that", "the", "their", "them", "themselves", "then", "thence", "there", "thereafter", "thereby", "therefore", 
			"therein", "thereupon", "these", "they", "thickv", "thin", "third", "this", "those", "though", "three", "through", "throughout", "thru", "thus", 
			"to", "together", "too", "top", "toward", "towards", "twelve", "twenty", "two", "un", "under", "until", "up", "upon", "us", "very", "via", "was", 
			"we", "well", "were", "what", "whatever", "when", "whence", "whenever", "where", "whereafter", "whereas", "whereby", "wherein", "whereupon", 
			"wherever", "whether", "which", "while", "whither", "who", "whoever", "whole", "whom", "whose", "why", "will", "with", "within", "without", 
			"would", "yet", "you", "your", "yours", "yourself", "yourselves", "the");
		$this->objects = array();
		$this->totalObjects = 0;
		$this->classifications = array();
		$this->words = array();
	}

	public function sanitize( $string ) {
		// to lower
		$string = strtolower( $string );
    	// strip html tags
    	$string = strip_tags( $string );
    	// strip out 's (like king's)
    	$string = str_replace( "'s", "", $string );
    	//decode into utf8
    	$string = str_replacE( "?s", "", $string );
    	$string = utf8_decode( $string );
    	//
    	$string = $this->htmlallentities( $string );
    	// Clean up things like &amp;
    	$string = html_entity_decode( $string );
    	// Strip out any url-encoded stuff
    	$string = urldecode( $string );
    	// Replace non-AlNum characters with space
    	//$string = preg_replace('/[^A-Za-z0-9]-&/', '', $string);
    	$string = preg_replace( "/[^A-Za-z[:space:]]/", "", $string );
    	//strip all filler words
    	$string = $this->strip_filler( $string );
    	// Replace Multiple spaces with single space
    	$string = preg_replace( '/ +/', ' ', $string );
    	// Trim the string of leading/trailing space
    	$string = trim( $string );
    	return $string;
  	}

	private function htmlallentities($str){
		$res = '';
		$strlen = strlen($str);
		for( $i = 0; $i < $strlen; $i++ ) {
			$byte = ord($str[$i]);
			if ( $byte < 128 ) { // 1-byte char
				$res .= $str[$i];
			} elseif ( $byte < 192 ) {
				// invalid utf8
			} elseif( $byte < 224 ) { // 2-byte char
				$res .= '&#'.((63&$byte)*64 + (63&ord($str[++$i]))).';';
		  	} elseif( $byte < 240 ) { // 3-byte char
		    	$res .= '&#'.((15&$byte)*4096 + (63&ord($str[++$i]))*64 + (63&ord($str[++$i]))).';';
		  	} elseif($byte < 248) { // 4-byte char
		    	$res .= '&#'.((15&$byte)*262144 + (63&ord($str[++$i]))*4096 + (63&ord($str[++$i]))*64 + (63&ord($str[++$i]))).';';
		    }
		}
		return $res;
	}
	
	private function strip_filler( $string ) {
    	foreach ( $this->stopwords as $stopword ) {
      		$stopword_between = ' ' . $stopword . ' ';
      		$string = str_replace( $stopword_between, ' ', $string );
      		//check for stopword at beginning and end of string
      		$a = substr( $string, 0, ( strlen( $stopword ) + 1 ) ); //the stopword is at the beginning of the title
      		if ( $a == ( $stopword . ' ') ) {
      			$string = substr( $string, strlen( $stopword ) + 1 );
      		}
      		$b = substr( $string, ( ( strlen( $stopword ) + 1 ) * -1 ) ); //the stopwrod is at the end of the title
      		if ( $b == ( ' ' . $stopword ) ) {
      			$string = substr( $string, 0, (-1 * ( strlen( $stopword ) +1 ) ) );
      		}
    	}
    	return $string;
	}

	private function dbConnect() {
    	$link = new mysqli($this->dbHost, $this->dbUser, $this->dbPass, $this->dbName);
    	if (mysqli_connect_errno()) {
      		echo "Connection failed: " . mysqli_connect_error() . "<br/>";
      		exit();
    	}
		return $link;
  	}

	private function createWordMapping( ) {
		$link = $this->dbConnect();
		$query = "
			CREATE TABLE IF NOT EXISTS `" . $this->wordMappingTableName . "` (
			  `dateModified` datetime NOT NULL,
			  `word` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
			  `classification` int(11) NOT NULL,
			  `yes` int(11) NOT NULL,
			  `no` int(11) NOT NULL,
			  PRIMARY KEY (`dateModified`, `word`, `classification`)
			)
		";
		$result = $link->query($query) or die($link->error.__LINE__);
		return true;
	}

	private function fetchObjects( ) {
		$link = $this->dbConnect();

		$query = "SELECT " . $this->objectTitleColumnName . 
			", " . $this->objectClassColumnName . 
			" FROM " . $this->objectTableName . 
			" WHERE " . $this->objectDateColumnName . " >= DATE_SUB(NOW(), INTERVAL " . $this->daysBack . " DAY)";

	    $result = $link->query($query) or die($link->error.__LINE__);

	    $objects = array();

	    while ($obj = $result->fetch_object()) {
	      array_push($objects, $obj);
	    }

	    $this->objects = $objects;

	    $this->totalObjects = sizeof( $this->objects );

	    $link->close();
	}

	private function setClassifications( ) {
		$classifications = array();

		foreach ( $this->objects as $object ) {
			if ( $classifications[ $object->{$this->objectClassColumnName} ] == null ) {
				$classifications[ $object->{$this->objectClassColumnName} ][ 'yes' ] = 1;
				$classifications[ $object->{$this->objectClassColumnName} ][ 'no' ] = $this->totalObjects - 1;
			} else {
				$classifications[ $object->{$this->objectClassColumnName} ][ 'yes' ]++;
				$classifications[ $object->{$this->objectClassColumnName} ][ 'no' ]--;
			}
		}

		$this->classifications = $classifications;

		return true;
	}

	private function contains( $string, $word ) {
		//check to see if the object title contains a specific word
		$string = "|" . $string . "|";
        $wordCheck1 = ' ' . $word . ' '; //word between 2 other words
        $wordCheck2 = "|" . $word . ' '; //word is at beginning of sentence
        $wordCheck3 = ' ' . $word . "|"; //word is at end of sentence
        if ( strpos( $string, $wordCheck1 ) !== false || strpos( $string, $wordCheck2 ) !== false || strpos( $string, $wordCheck3 ) !== false ) {
        	return true;
        }
        return false;
	}

	private function setNo( $words, $wordCounts ) {
		foreach ( $words as $word => &$classifications ) {
			foreach ($classifications as $classification => &$values ) {
				//set 'no' equal to the total number of occurrences of this word - the number of times this word was in this classification
				//(eg. no = #object titles containing this word NOT in this category)
				$values[ 'no' ] = $wordCounts[ $word ] - $values[ 'yes' ];
			}
		}

		return $words;
	}

	private function setWordsForMapping( ) {
		$words = array();
		$wordCounts = array();

		foreach ( $this->objects as $object ) {
			//loop through objects and strip each title into component words
			//update the words array based on the classification of the object's title
			$title_words = array_unique( explode(' ', $this->sanitize( $object->{$this->objectTitleColumnName} ) ) );
			
			foreach ( $title_words as $word ) {
				$wordCounts[ $word ]++;
				$words[ $word ][ $object->{$this->objectClassColumnName} ][ 'yes' ]++;
			}
		}

		$words = $this->setNo( $words, $wordCounts );

		$this->words = $words;
	}

	private function fetchWordValues( $word, $classification ) {

		$word_values = array();

		$link = $this->dbConnect();

		$query = "SELECT yes, no" . 
			" FROM " . $this->wordMappingTableName .
			" WHERE `classification` = ?" . 
			" AND `word` = ?" . 
			" AND `dateModified` >= DATE_SUB( NOW(), INTERVAL " . $this->daysBack . " DAY )";

		if ( !( $stmt = $link->prepare( $query ) ) ) {
			echo "Prepare failed: (" . $mysqli->errno . ") " . $mysqli->error;
		}
	    $stmt->bind_param( "is", $classification, $word );
	    $stmt->execute(  );
	    $stmt->store_result(  );
	    $num_word_rows = $stmt->num_rows;
	    //bind result
	    $stmt->bind_result( $yes, $no );
	    $stmt->fetch(  );
	    //close and reset
	    $stmt->reset(  );
	    $stmt->close(  );

	    if( $num_word_rows > 0) {
	    	$word_values[ 'yes' ] = $yes;
	    	$word_values[ 'no' ] = $no;
	    	return $word_values;
	    }
	    return false;
	}

	private function joint_conditional_probability( $vals ) {
		$product = 1;
		foreach ( $vals as $val ) {
			$product = $product * $val;
		}
		return $product;
	}

	public function classify( $string ) { 
		//fed a string, return a classification
		$string = $this->sanitize( $string );
		$words = explode( " ", $string );

		//fetch the classifications
		if ( empty( $this->classifications ) ) {
			//the objects variable is empty if classification was not preceeded by train
			//fetch objects from past daysBack days
			$this->fetchObjects();
			//set the classifications variable
			$this->setClassifications();
		}

		$yes = array();
		$no = array();
		$p_classifications = array();

		foreach ( $this->classifications as $classification => $value ) {
			foreach ( $words as $word ) {
				//get the word / classification combo
				$word_values = $this->fetchWordValues( $word, $classification );
				if ( $word_values !== FALSE ) { //there are values for this word in this classification
					array_push( $yes, ( $word_values[ 'yes' ] / $value[ 'yes' ] ) );
					array_push( $no , ( $word_values[ 'no' ] / ( $this->totalObjects - $value[ 'yes' ] ) ) );
				}
			}
			if ( ! empty( $yes ) && ! empty( $no ) ) {
				array_push( $yes, ( $value[ 'yes' ] / ( $this->totalObjects )));
				array_push( $no , ( $value[ 'no' ] / ( $this->totalObjects )));

				$joint_prob_yes = $this->joint_conditional_probability( $yes );
				$joint_prob_no = $this->joint_conditional_probability( $no );

				$prop_yes = $joint_prob_yes / ( $joint_prob_yes + $joint_prob_no );
				$prop_no = $joint_prob_no / ( $joint_prob_yes + $joint_prob_no );

				$p_classifications[ $classification ][ 'yes' ] = $prop_yes;
				$p_classifications[ $classification ][ 'no' ] = $prop_no;

			}
		}

	}

	private function emptyMappingTable( ) {
		$link = $this->dbConnect();

		$query = "DELETE FROM " . $this->wordMappingTableName;
		if ( !( $stmt = $link->prepare( $query ) ) ) {
			echo "Prepare failed: (" . $mysqli->errno . ") " . $mysqli->error;
			return false;
		}
	    $stmt->execute(  );
	    $stmt->reset(  );
	    $stmt->close(  );

	    return true;

	}

	private function upsert( $word, $classifications ) {

		$link = $this->dbConnect();

		$classification_ids = array_keys( $classifications );

		foreach ( $classification_ids as $classification ) { //loop through the different classifications for the word
	    	//this word doesn't exist in the mapping, insert it
	    	$query = "INSERT INTO " . $this->wordMappingTableName . 
	    		"( dateModified, word, classification, yes, no)" . 
	    		" VALUES ( NOW() , ?, ?, ?, ?)";

			if ( !( $stmt = $link->prepare( $query ) ) ) {
				echo "Prepare failed: (" . $mysqli->errno . ") " . $mysqli->error;
			}

	    	$stmt->bind_param( "siii", 
	    		$word, 
	    		$classification, 
	    		$classifications[ $classification ][ 'yes' ], 
	    		$classifications[ $classification ][ 'no' ] 
	    	);
	    	$stmt->execute(  );
	    	$stmt->close(  );
		}

	    $link->close();

	    return true;

	}

	public function train( ) {
		//create the word mapping table. Does nothing if the table already exists
		$this->createWordMapping(  );
		//fetch objects from past daysBack days
		$this->fetchObjects();
		//set the classifications variable
		$this->setClassifications();
		//set the titles variable
		$this->setWordsForMapping();
		//empty the mappings table
		$this->emptyMappingTable();

		foreach ( $this->words as $key => $value ) {
			$this->upsert( $key, $value );
		}
	}
}

?>