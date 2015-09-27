<?php
require_once( __DIR__ . '/parallelcurl.php' );
require_once( __DIR__ . '/CCache.php' );
require_once( __DIR__ . '/Enum.php' );
//=========================================================================================
//	CGW2API by Chippalrus
//=========================================================================================
class CGW2API extends CCache
{
//=========================================================================================
//	Members
//=========================================================================================
	private		$m_ParallelCurl		=	null;
	private		$m_Content			=	null;
//=========================================================================================
//	Constructor / Destructor / Clean Up
//=========================================================================================
	//	$iParallelProcesses is the number of parallel processes allowed by CURL
	protected	function	__construct		( $iParallelProcesses )
	{
		$this->m_ParallelCurl		=	new ParallelCurl( $iParallelProcesses );
		parent::__construct( 'api_cache' );
		$this->m_Content			=	Array();
	}
	
	public	function	__destruct()
	{
		unset( $this->m_Content );
	}
	
	protected	function	CleaUp()
	{
		unset( $this->m_Content );
		$this->m_Content = Array();
	}
//=========================================================================================
//	Get
//=========================================================================================
	//		For single calls to the API -- json
	public	function	GetContent( $iID, $eURI )
	{
		//	Clear the content ParallelCurl callback sets.
		$this->CleaUp();
		//	Checks for valid input
		if( isset( $iID ) )
		{
			// Check if Cache exists or is expired.
			if( $this->IsExpired( $iID, $eURI ) )
			{
				// Send request and write the cache.
				$this->SendRequest( $iID, $eURI );
				$this->WriteCache( $iID, $this->m_Content[ 0 ], $eURI );
			}
			else	
			{
				// Cache exists so use cache instead.
				array_push( $this->m_Content, $this->GetCache( $iID, $eURI ) );
			}
		}
		// Return the Cache or Requested content.
		return $this->m_Content[ 0 ];
	}
	
	//		For Batch calls to the API -- array json
	public	function	GetContentBatch( $aID, $eURI )
	{
		//	Clear the content ParallelCurl callback sets.
		$this->CleaUp();
		//	Checks for valid input
		if( !is_null( $aID ) )
		{
			// Temp list for Requests without a cache
			$aList = Array();
			// Temp list for Requests with a cache
			$tempContent = Array();
			// Loop through batch IDs
			for( $i = 0; $i < count( $aID ); $i++ )
			{
				// Check if Cache exists or is expired.
				if( $this->IsExpired( $aID[ $i ], $eURI ) )
				{
					// Store into List for Request
					array_push( $aList, $aID[ $i ] );
				}
				else
				{
					// Store Cache files into Temp list
					array_push( $tempContent, $this->GetCache( $aID[ $i ], $eURI ) );
				}
			}
			// If Request list is not empty
			$iLength = count( $aList );
			if( $iLength > 0 )
			{
				// Request for the content
				$this->SendRequestList( $aList, $eURI );
				// Loop through the Requested content
				for( $i = 0; $i < $iLength; $i++ )
				{
					// Write each to Cache
					$this->WriteCache( $aList[ $i ], $this->m_Content[ $i ], $eURI );
				}
			}
			// Merge Requested content with Cached content.
			$this->m_Content = array_merge( $this->m_Content, $tempContent );
		}
		// Return the merged list.
		return $this->m_Content;
	}
	
	public	function	GetCharacter( $sName, $sToken )	// json
	{
		$this->CleaUp();
		if( $this->IsExpired( $sName, EURI::CHARACTERS ) )
		{
			if( !is_null( $sName ) && !is_null( $sToken ) )
			{
				$this->SendRequest( $sToken, EURI::CHARACTERS . '/' . $sName . EURI::ACCESS_TOKEN );
				$this->WriteCache( $sName, $this->m_Content[ 0 ], EURI::CHARACTERS );
			}
		}
		else	
		{
			array_push( $this->m_Content, $this->GetCache( $sName, EURI::CHARACTERS ) );
		}
		return $this->m_Content[ 0 ];
	}
//=========================================================================================
//	Functions
//=========================================================================================
	//	callback function for ParallelCurl
	public	function	RequestCompleted( $content, $url, $ch, $search )
	{
		// Adds requested content
		array_push( $this->m_Content, $content );
	}

	//	For single request calls using ParallelCurl
	private	function	SendRequest( $iID, $eURI )
	{
		$this->m_ParallelCurl->startRequest
		(
			EURI::BASE . $eURI . $iID,			// URI
			array( $this, 'RequestCompleted' )	// Callback
		);
		$this->m_ParallelCurl->finishAllRequests();	// Called to finish requests
	}
	
	//	For batch request calls using ParallelCurl
	private	function	SendRequestList( $iID, $eURI )
	{
		for( $i = 0; $i < count( $iID ); $i++ )
		{
			if( !is_null( $iID[ $i ] ) )
			{
				$this->m_ParallelCurl->startRequest
				(
					EURI::BASE . $eURI . $iID[ $i ],	// URI
					array( $this, 'RequestCompleted' )	// Callback
				);
			}
		}
		$this->m_ParallelCurl->finishAllRequests();		// Called to finish requests
	}
}
?>
