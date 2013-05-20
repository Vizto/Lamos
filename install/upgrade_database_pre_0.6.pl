#!/usr/bin/perl 

use strict;
use warnings;

$::PROG = "upgrade_database_pre_0.6.pl";
$::VERSION = "1.01";
$::debug = 0;

# set global strings for getopt
sub main::HELP_MESSAGE {
    print "Usage: $::PROG [options] <source file 1> <source file 2> <target file>\n".
          "  where:\n".
          "   source file 1  is a sql dump that contains the 'lms_cat' table,\n".
          "   source file 2  is a sql dump that contains the 'lms_stat_cat' table and\n".
          "   target file    is the sql import file to be created\n".
          "\n".
          "  Each source file must not contain data from other tables or ". 
          "the conversion may produce wrong results.\n".
          "\n".
          "Options:\n".
          "   -d   --debug       enable debug output\n".
          "   -v   --version     show program version\n".
          "   -h   --help        show this help\n\n";
}

while (defined $ARGV[0] and $ARGV[0] =~ s/^-//) {
    if ($ARGV[0] =~ /^(v|-version)$/) {
        print("$::PROG v$::VERSION\n");
        exit 0;
    } elsif ($ARGV[0] =~ /^(h|-help|\?)$/) {
        print("$::PROG v$::VERSION\n");
        main::HELP_MESSAGE();
        exit 0;
    } elsif ($ARGV[0] =~ /^(d|-debug)$/) {
        $::debug = 1;
        shift;
    } else {
        print "ERROR: unknown option\n\n";
        print "Type '$::PROG --help' for more information\n\n";
        exit 1;
    }
}

if ($#ARGV != 2) {
    print "ERROR: missing filename\n\n";
    main::HELP_MESSAGE();
    exit 1;
}
my $file_source_cat = $::ARGV[0];
my $file_source_stat = $::ARGV[1];
my $file_target_stat = $::ARGV[2];


%::cats = ();
%::stat = ();
$::tablename = "";
open(SC, "<", $file_source_cat) or die;
my $line;
while ($line=<SC>) {
	chomp($line);
# not working with fields containing generic strings
#        my ($catid, $parentid, $name, $link);
#        my %tempkey = ();
#        my %tempval = ();
#	next unless ($line =~ m/^INSERT INTO .* \(`?([^,`]+)`?, ?`?([^,`]+)`?, ?`?([^,`]+)`?, ?`?([^,`]+)`?\) VALUES \('?(.+)'?,'?(.+)'?,'?(.+)'?,'?(.+)'?\);/);
#        $tempkey{"1"} = $1;
#        $tempkey{"2"} = $2;
#        $tempkey{"3"} = $3;
#        $tempkey{"4"} = $4;
#        $tempval{"1"} = $5;
#        $tempval{"2"} = $6;
#        $tempval{"3"} = $7;
#        $tempval{"4"} = $8;
#        for (my $i=1; $i<5; $i++) {
#            if ($tempkey{"$i"} eq "catid") {
#                $catid = int($tempval{"$i"});
#            } elsif ($tempkey{"$i"} eq "parentid") {
#                $parentid = int($tempval{"$i"});
#            } elsif ($tempkey{"$i"} eq "name") {
#                $name = $tempval{"$i"};
#            } elsif ($tempkey{"$i"} eq "link") {
#                $link = int($tempval{"$i"});
#            } 
#        }
	next unless ($line =~ m/^INSERT INTO .* \(`?catid`?, ?`?parentid`?, ?`?name`?, ?`?link`?\) VALUES \('?([0-9]+)'?,'?([0-9]+)'?,'?(.+)'?,'?([0-9]+)'?\);/);
        my $catid = $1;
        # create assoc. array containing all cat data from db
        $::cats{"$catid"}{"parent"} = $2;
        $::cats{"$catid"}{"name"} = $3;
        $::cats{"$catid"}{"link"} = $4;
}
close(SC);
$line = "";
open(SS, "<", $file_source_stat) or die;
while ($line=<SS>) {
	chomp($line);
#        print "processing $line ...\n";
        my ($catid, $userid, $cnt, $sort);
        my %tempkey = ();
        my %tempval = ();
	next unless ($line =~ m/^INSERT INTO `?([^`]+)`? \(`?([^,`]+)`?, ?`?([^,`]+)`?, ?`?([^,`]+)`?, ?`?([^,`]+)`?\) VALUES \('?([0-9]+)'?,'?([0-9]+)'?,'?([0-9]+)'?,'?([0-9]+)'?\);/);
        $::tablename = $1;
        $tempkey{"1"} = $2;
        $tempkey{"2"} = $3;
        $tempkey{"3"} = $4;
        $tempkey{"4"} = $5;
        $tempval{"1"} = $6;
        $tempval{"2"} = $7;
        $tempval{"3"} = $8;
        $tempval{"4"} = $9;
        for (my $i=1; $i<5; $i++) {
            if ($tempkey{"$i"} eq "catid") {
                $catid = $tempval{"$i"};
            } elsif ($tempkey{"$i"} eq "userid") {
                $userid = $tempval{"$i"};
            } elsif ($tempkey{"$i"} eq "catcount") {
                $cnt = $tempval{"$i"};
            } elsif ($tempkey{"$i"} eq "catsort") {
                $sort = $tempval{"$i"};
            } 
        }
        # create assoc. array containing all cat data from db
        $::stat{"$userid"}{"$catid"}{"sort"} = $sort;
        $::stat{"$userid"}{"$catid"}{"count"} = $cnt;
        # complete tree with data from lms_cat table
        $::stat{"$userid"}{"$catid"}{"parent"} = $::cats{"$catid"}{"parent"};
        $::stat{"$userid"}{"$catid"}{"name"} = $::cats{"$catid"}{"name"};
        $::stat{"$userid"}{"$catid"}{"link"} = $::cats{"$catid"}{"link"};
}
close(SS);


sub build_cat_tree() {
    # find child categories
    # WARNING: this reverse lookup is very resource intensive and is not 
    #          recommended for use with many categories
    foreach my $u (keys %::stat) {
        print("DEBUG: processing user: ",$u,"\n") if ($::debug > 0);
        foreach my $pc (0, sort keys %{$::stat{"$u"}}) {
            print("DEBUG: ... processing parent category: ",$pc,"\n") if ($::debug > 0);
            my %chld = ();
            foreach my $c (keys %{$::stat{"$u"}}) {
                print("DEBUG: ... ... scanning category: ",$c,"\n") if ($::debug > 0);
                if (exists($::stat{"$u"}{"$c"}{"parent"}) and $::stat{"$u"}{"$c"}{"parent"} == $pc) {
                    print("DEBUG: ... ... found child: ",$c," (sort order ",$::stat{"$u"}{"$c"}{"sort"},")\n") if ($::debug > 0);
                    $chld{"$c"} = $::stat{"$u"}{"$c"}{"sort"};
                }
            }
            # create linked list of child categories
            my $prev = "first";
            # sort by array valued not by keys (thereby using the sort order from the db)
            foreach my $c (sort {$chld{$a} <=> $chld{$b}} keys %chld) {
                if ($prev eq "first") {
                    if ($pc != 0) { 
                        print("DEBUG: ... ... ... linking initial child ",$c," to parent ",$pc," (prev = ",$prev,")\n") if ($::debug > 0);
                        $::stat{"$u"}{"$pc"}{"childs"} = $c;
                    } else {
                        print("DEBUG: ... ... ... initial child ",$c," not linked to top-level parent ",$pc," (prev = ",$prev,")\n") if ($::debug > 0);
                    }
                    $::stat{"$u"}{"$c"}{"prev"} = 0;
                } else {
                    print("DEBUG: ... ... ... linking child ",$c," to previous child ",$prev,"\n") if ($::debug > 0);
                    $::stat{"$u"}{"$prev"}{"next"} = $c;
                    $::stat{"$u"}{"$c"}{"prev"} = $prev;
                }
                $prev = $c;
            }
            if ($prev ne "first") {
                print("DEBUG: ... ... ... linking last child ",$prev," to empty next child 0","\n") if ($::debug > 0);
                $::stat{"$u"}{"$prev"}{"next"} = 0;
            }
        }
    }

}



# build cat tree
build_cat_tree();

open(TS, ">", $file_target_stat) or die;

print(TS "DROP TABLE IF EXISTS `". $::tablename ."`;\n");
print(TS "CREATE TABLE `". $::tablename ."` (\n");
print(TS "  `catid` integer NOT NULL,\n");
print(TS "  `userid` integer NOT NULL,\n");
print(TS "  `catcount` integer NOT NULL DEFAULT '0',\n");
print(TS "  `catnext` integer NOT NULL DEFAULT '0',\n");
print(TS "  `catprev` integer NOT NULL DEFAULT '0',\n");
print(TS "  PRIMARY KEY (`catid`, `userid`)\n");
print(TS ") ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;\n\n");

foreach my $u (sort {$a <=> $b} keys %::stat) {
    print("\nDEBUG: writing user: ",$u,"\n") if ($::debug > 0);
    foreach my $c (sort {$a <=> $b} keys %{$::stat{"$u"}}) {
        print("DEBUG: ... writing category: ",$c,"\n") if ($::debug > 0);
        print("DEBUG: ... ... count = ",$::stat{"$u"}{"$c"}{"count"},"\n") if ($::debug > 0);
        print("DEBUG: ... ... next = ",$::stat{"$u"}{"$c"}{"next"},"\n") if ($::debug > 0);
        print("DEBUG: ... ... prev = ",$::stat{"$u"}{"$c"}{"prev"},"\n") if ($::debug > 0);
        printf(TS 'INSERT INTO `%s` (`catid`, `userid`, `catcount`, `catnext`, `catprev`) VALUES (%d,%d,%d,%d,%d);'."\n", $::tablename, $c, $u, $::stat{"$u"}{"$c"}{"count"}, $::stat{"$u"}{"$c"}{"next"}, $::stat{"$u"}{"$c"}{"prev"});
    }
}
print(TS "\n");
close(TS);


