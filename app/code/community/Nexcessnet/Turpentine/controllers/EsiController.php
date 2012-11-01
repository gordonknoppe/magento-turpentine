<?php

/**
 * Nexcess.net Turpentine Extension for Magento
 * Copyright (C) 2012  Nexcess.net L.L.C.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 */

class Nexcessnet_Turpentine_EsiController extends Mage_Core_Controller_Front_Action {
    public function indexAction() {
        return $this->getBlockAction();
    }

    public function getBlockAction() {
        if( !Mage::helper( 'turpentine/esi' )->getEsiEnabled() ) {
            Mage::throwException( 'ESI includes are not enabled' );
        }
        $esiDataId = $this->getRequest()->getParam( 'id' );
        $cache = Mage::app()->getCache();
        if( $esiData = @unserialize( $cache->load( $esiDataId ) ) ) {
            if( $registry = $esiData->getRegistry() ) {
                foreach( $registry as $key => $value ) {
                    Mage::register( $key, $value );
                }
            }
        } else {
            //block data not in the cache, figure out how to regenerate and
            // cache it, or throw exception for now
            Mage::throwException( 'Block data missing from cache for ID: ' .
                $esiDataId );
        }
        $layout = Mage::getSingleton( 'core/layout' );
        $design = Mage::getSingleton( 'core/design_package' )
            ->setPackageName( $esiData->getDesignPackage() )
            ->setTheme( $esiData->getDesignTheme() );
        $layoutXml = $layout->getUpdate()->getFileLayoutUpdatesXml(
            $design->getArea(),
            $design->getPackageName(),
            $design->getTheme( 'layout' ),
            $esiData->getStoreId() );

        $handleNames = $layoutXml->xpath( sprintf(
            '//block[@name=\'%s\']/ancestor::node()[last()-2]',
            $esiData->getNameInLayout() ) );
        foreach( $handles as $handle ) {
            $handleName = $handle->getName();
            $layout->getUpdate()->addHandle( $handleName );
            $layout->getUpdate()->load();
            $layout->generateXml();
            $layout->generateBlocks();

            if( $block = $layout->getBlock( $esiData->getNameInLayout() ) ) {
                $block->setEsi( false );
                $this->getResponse()->setBody( $block->toHtml() );
                break;
            }
            //is this line really needed?
            Mage::app()->removeCache( $layout->getUpdate()->getCacheId() );
            $layout->getUpdate()->removeHandle( $handleName );
            $layout->getUpdate()->resetUpdates();
        }
    }

    public function getMessagesAction() {
        if( !Mage::helper( 'turpentine/esi' )->getEsiEnabled() ) {
            Mage::throwException( 'ESI includes are not enabled' );
        }
        $responseHtml = '';
        foreach( array( 'catalog/session', 'checkout/session' ) as $className ) {
            if( $session = Mage::getSingleton( $className ) ) {
                $this->loadLayout();
                $messageBlock = $this->getLayout()->getMessagesBlock();
                $messageBlock->addMessages( $session->getMessages( true ) );

                $messageBlock->setEsi( false );
                if( $messageHtml = $messageBlock->toHtml() ) {
                    //set no cache flag
                    $responseHtml .= $messageHtml;
                }
            }
        }
        $this->getResponse()->setBody( $responseHtml );
    }
}