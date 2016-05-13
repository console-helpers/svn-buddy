-- The "PluginData" table
CREATE TABLE "PluginData" (
	"Name" text(255,0) NOT NULL,
	"LastRevision" integer,
	PRIMARY KEY("Name")
);

-- The "Projects" table
CREATE TABLE "Projects" (
	"Id" integer NOT NULL PRIMARY KEY AUTOINCREMENT,
	"Path" text(255,0) NOT NULL,
	"BugRegExp" text,
	"IsDeleted" integer NOT NULL DEFAULT 0
);

CREATE UNIQUE INDEX "IDX_PROJECTS_PATH" ON Projects ("Path" ASC);

-- The "ProjectRefs" table
CREATE TABLE "ProjectRefs" (
	"Id" integer NOT NULL PRIMARY KEY AUTOINCREMENT,
	"ProjectId" integer NOT NULL,
	"Name" text(100,0) NOT NULL,
	FOREIGN KEY ("ProjectId") REFERENCES "Projects" ("Id")
);

CREATE UNIQUE INDEX "IDX_PROJECTREFS_NAME" ON ProjectRefs ("ProjectId" ASC, "Name" ASC);

-- The "Commits" table
CREATE TABLE "Commits" (
	"Revision" integer NOT NULL,
	"Author" text(50,0),
	"Date" integer NOT NULL,
	"Message" text,
	PRIMARY KEY("Revision")
);

-- The "CommitProjects" table
CREATE TABLE "CommitProjects" (
	"Revision" integer NOT NULL,
	"ProjectId" integer NOT NULL,
	PRIMARY KEY("Revision","ProjectId"),
	CONSTRAINT "FK_COMMITPROJECTS_PROJECTID" FOREIGN KEY ("ProjectId") REFERENCES "Projects" ("Id"),
	CONSTRAINT "FK_COMMITPROJECTS_REVISION" FOREIGN KEY ("Revision") REFERENCES "Commits" ("Revision")
);

-- The "CommitRefs" table
CREATE TABLE "CommitRefs" (
	"Revision" integer NOT NULL,
	"RefId" integer NOT NULL,
	PRIMARY KEY("Revision","RefId"),
	CONSTRAINT "FK_COMMITREFS_REFID" FOREIGN KEY ("RefId") REFERENCES "ProjectRefs" ("Id"),
	CONSTRAINT "FK_COMMITREFS_REVISION" FOREIGN KEY ("Revision") REFERENCES "Commits" ("Revision")
);

-- The "Paths" table
CREATE TABLE "Paths" (
	"Id" integer NOT NULL PRIMARY KEY AUTOINCREMENT,
	"Path" text(255,0) NOT NULL,
	"PathNestingLevel" integer NOT NULL DEFAULT 0,
	"PathHash" integer NOT NULL,
	"RefName" text(100,0) NOT NULL DEFAULT (''),
	"ProjectPath" text(255,0) NOT NULL DEFAULT (''),
	"RevisionAdded" integer NOT NULL,
	"RevisionDeleted" integer,
	"RevisionLastSeen" integer,
	CONSTRAINT "FK_PATHS_REVISIONADDED" FOREIGN KEY ("RevisionAdded") REFERENCES "Commits" ("Revision"),
	CONSTRAINT "FK_PATHS_REVISIONDELETED" FOREIGN KEY ("RevisionDeleted") REFERENCES "Commits" ("Revision"),
	CONSTRAINT "FK_PATHS_REVISIONLASTSEEN" FOREIGN KEY ("RevisionLastSeen") REFERENCES "Commits" ("Revision")
);

CREATE UNIQUE INDEX "IDX_PATHS_PATH" ON Paths ("Path" ASC);
CREATE UNIQUE INDEX "IDX_PATHS_PATHHASH" ON Paths ("PathHash" ASC);

-- The "CommitPaths" table
CREATE TABLE "CommitPaths" (
	"Revision" integer NOT NULL,
	"Action" text(1,0) NOT NULL,
	"Kind" text(4,0) NOT NULL,
	"PathId" integer NOT NULL,
	"CopyRevision" integer,
	"CopyPathId" integer,
	PRIMARY KEY("Revision","PathId"),
	CONSTRAINT "FK_COMMITPATHS_REVISION" FOREIGN KEY ("Revision") REFERENCES "Commits" ("Revision"),
	CONSTRAINT "FK_COMMITPATHS_PATHID" FOREIGN KEY ("PathId") REFERENCES "Paths" ("Id"),
	CONSTRAINT "FK_COMMITPATHS_COPYREVISION" FOREIGN KEY ("CopyRevision") REFERENCES "Commits" ("Revision")
);

CREATE INDEX "IDX_COMMITS_PATHID" ON CommitPaths ("PathId" ASC);

-- The "CommitBugs" table
CREATE TABLE "CommitBugs" (
	"Revision" integer NOT NULL,
	"Bug" text NOT NULL,
	PRIMARY KEY("Revision","Bug"),
	CONSTRAINT "FK_COMMITBUGS_REVISION" FOREIGN KEY ("Revision") REFERENCES "Commits" ("Revision")
);

-- The "Merges" table
CREATE TABLE "Merges" (
	"MergeRevision" integer NOT NULL,
	"MergedRevision" integer NOT NULL,
	PRIMARY KEY("MergeRevision","MergedRevision"),
	CONSTRAINT "FK_MERGES_MERGEDREVISION" FOREIGN KEY ("MergedRevision") REFERENCES "Commits" ("Revision"),
	CONSTRAINT "FK_MERGES_MERGEREVISION" FOREIGN KEY ("MergeRevision") REFERENCES "Commits" ("Revision")
);
