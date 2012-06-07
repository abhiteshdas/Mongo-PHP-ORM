<?

/*

  Mongo related *uncommon* functions.

*/

class M_Helper {


    // show all lv1 & 2 fields from collections
    // by default lists only unknown fields
    static function fields($collection, $hide_known_fields=true, $lv2=true) {
        $field=array();
        $ci=M($collection)->find( array() ); // iterate over everthing
        $known=M($collection)->config("field-type");
        foreach($ci as $row) {
            foreach($row as $f => $v) {
                if (is_numeric($f))
                    continue;
                if ($hide_known_fields && $known[$f])
                    continue;
                $field[$f]++;
                if ( ! is_array($v) )
                    continue;
                if (! $lv2) continue;
                foreach($v as $f2 => $v) {
                    if (is_numeric($f2))
                        continue;
                if ($hide_known_fields && $known["$f.$f2"])
                    continue;
                    $field["$f.$f2"]++;
                }
            }
        }
        asort($field);
        return $field;
    }

    // apply fields() to all collections in db
    static function dbCollectionFields($db, $raw=false, /*string*/ $skip_collections="") {
        $r=array();
        $skip=array_flip(explode(" ", $skip_collections));
        foreach( M()->$db->listCollections() as $c ) {
            $c="".$c;
            if (isset($skip[$c]) )
                continue;
            $r[$c]=self::fields($c);
        }
        if ( $raw)
            return $r;
        foreach($r as $c => $d) {
            $cnt=$d["_id"]; 
            unset($d["_id"]);
            echo "$c ($cnt)\n";
            if (! $d)
                continue;
            echo "    ".json_encode($d, "\n    ")."\n\n";
        }
    }

    // remove fields from collection
    // fields = space delimited field lists
    static function unsetFields(/*string*/ $collection, /*string*/ $fields) {
        $F=array_flip(M::qw($fields)); // field => isset
        $C=M($collection);
        $ci=$C->find( array() ); // iterate over everthing
        foreach($ci as $id => $v) {  // WTF - WHY ID is STRING?? when it is INT
            $id=$v["_id"]; // right type
            foreach($v as $f => $x) {
                #vd($f, $id, $F);
                if ( isset($F[$f]) ) {
                    $C->update(array("_id" => $id),
                               array('$unset' => array($f => 1))
                               );

                    #var_dump($v);
                    #vd("id=$id f=$f");

                }
            }
        }

    }


    // Migrate MYSQL table to Mongo
    //
    // $source           - "db.table" | "select query"
    // $mongo_collection - "db.collection"
    // $int_fields - space demilited int fields list
    // $pk = primary key
    static function migrateTable($source, $mongo_collection,
                                  $pk="id",
                                  $int_fields="", $float_fields="",
                                  /* callable */ $processor=null) { #
        Profiler::disable();

        echo "migrating $source to $mongo_collection\n";

        if (! starts_with($source, "select ") )
            $source="select * from $source";
        DBE()->dbh()->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY,0);
        $sth=DBE()->run($source);

        $M=M($mongo_collection);
        $M->remove( array() );
        if (! $pk) {
            if (Sequence::last($mongo_collection))
                Sequence::reset($mongo_collection);
            else
                Sequence::create($mongo_collection);
        }

        $i=0;
        $int_fields   = M::qw($int_fields);
        $float_fields = M::qw($float_fields);
        $ids=array();
        while($row = $sth->fetch(PDO::FETCH_ASSOC)) {
            $i++;
            foreach($int_fields as $field)
                $row[$field] = (int) $row[$field];
            foreach($float_fields as $field)
                $row[$field] = (float) $row[$field];
            if ($pk) {
                $row["_id"] = $row[$pk];
                unset($row[$pk]);
            } else {
                if (! $ids)
                    $ids = Sequence::next($mongo_collection, 100);
                $id=array_shift($ids);
                $row["_id"] = $id;
            }
            if ($processor)
                $processor(/* & */ $row);
            try {
                $M->insert($row);
            } catch(Exception $ex) {
                echo "id=$row[_id] - got exception: ".$ex->getMessage()."\n";
            }
            if (($i % 1000)==0) {
                echo ".";
                flush();
            }
        }
        echo "\n  rows: $i - done\n\n";
    }


    //
    // Missing GROUPBY for Mongo
    //
    // mc        - MongoCollection
    // group_by  - space delimited string
    // field_op  - space delimited string
    //   $field:$operation
    // result field name: $field_$operation
    // raw - return mongo group_by result (ok flag, count, keys, "retval")
    //
    // Supported operations:
    //  sum,count,min,max
    //
    // Examples:
    //  M("merchant.sale")->groupBy("sale:sum sale:max sale:count", "merchant_id", array("year" => 2011))
    //   ==  select merchant_id, sum(sale) from merchant.sale  where year=2011 group by merchant_id
    static function groupBy($mc, $field_op, $group_by="", array $where=array(), $raw=false) { # { $group_fields, $sum_fields }
        $initial=[];

        $r=""; // js part of reduce
        foreach(explode(" ", $field_op) as $fo) {
            list($f, $op)=explode(":", $fo);
            if (! $op) {
                trigger_error("bad format: $fo. 'field:operation' expected");
                die;
            }

            $fn=$f."_".$op;
            $initial[$fn]=0;
            if ($op=="min" || $op=="max")
                unset($initial[$fn]);

            switch($op) {
            case "sum":    $r.="p.$fn+=o.$f;"; break;
            case "count":  $r.="p.$fn++;"; break;
            case "min":  $r.="if (isNaN(p.$fn)) { p.$fn=o.$f } else { p.$fn=Math.min(p.$fn,o.$f)};"; break;
            case "max":  $r.="if (isNaN(p.$fn)) { p.$fn=o.$f } else { p.$fn=Math.max(p.$fn,o.$f)};"; break;
            default:
                trigger_error::alert("unknown operation: $op");
                die;
            }
        }

        $reduce="function(o,p){". $r . "}"; // REDUCE o = obj / p = prev

        // can also use finalize option
        //  as in "finalize: function(out){ out.avg_time = out.total_time / out.count }"

        $R=$mc->group(M::qk($group_by),
                      $initial,
                      $reduce,
                      array("condition" => $where)
                      );

        if ($R["ok"]!=1) {
            throw new RuntimeException("Mongo groupby fail\n  ".json_encode($R)."\n".
                       "    initial: ".json_encode($initial)."\n".
                       "    reduce: $reduce"
                          );
        }
        if (! isset($R["retval"]))
            return [];

        if ($raw)
            return $R;

        return $R["retval"];
    }

}

?>
